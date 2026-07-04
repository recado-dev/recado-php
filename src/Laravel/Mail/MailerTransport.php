<?php

declare(strict_types=1);

namespace Mailer\Sdk\Laravel\Mail;

use Illuminate\Contracts\Events\Dispatcher;
use Mailer\Sdk\Dto\BatchResult;
use Mailer\Sdk\Exception\MailerException;
use Mailer\Sdk\Exception\ValidationException;
use Mailer\Sdk\Laravel\Events\MessageSuppressed;
use Mailer\Sdk\MailerClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Symfony mail transport that routes Laravel's Mail facade through the Mailer
 * platform /send API. Registered as the "mailer" driver by the service
 * provider, so an app uses it with MAIL_MAILER=mailer.
 *
 * Behavior is documented in the README "Laravel integration" section. Key
 * decisions: From/Reply-To are ignored (the platform uses the project's
 * configured sender); attachments are unsupported (fail or ignore by config);
 * a suppressed recipient is NOT a failure (a {@see MessageSuppressed} event is
 * dispatched); quota/domain rejections ARE failures (raised as a Symfony
 * TransportException so Laravel can retry per its own policy).
 */
final class MailerTransport extends AbstractTransport
{
    /**
     * Error codes that are NOT transport failures: the platform refused the
     * recipient but the send itself was accepted.
     */
    private const SUPPRESSED_CODE = 'recipient_suppressed';

    private readonly ?Dispatcher $events;

    private readonly ?LoggerInterface $transportLogger;

    /**
     * @param array<string, mixed> $config The `mailer-sdk.mail` config block:
     *                                      `attachments` ('fail'|'ignore') and
     *                                      `idempotency` ('content'|'random'|'off').
     */
    public function __construct(
        private readonly MailerClient $client,
        private readonly array $config = [],
        ?Dispatcher $events = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct();

        $this->events = $events;
        $this->transportLogger = $logger;
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $recipients = $this->recipients($message);

        if ($recipients === []) {
            // Nothing to deliver; Symfony normally guards this, but stay safe.
            return;
        }

        $base = PayloadMapper::base($email, $this->config, $this->transportLogger);

        // Resolve only the explicit X-Mailer-Idempotency-Key override here; the
        // content key MUST be computed per recipient (single) / per recipient
        // list (batch), AFTER `to` is merged — otherwise identical content to
        // different recipients hashes to the same key and the platform silently
        // dedupes the later sends.
        $override = $this->header($email, MailerHeaders::IDEMPOTENCY_KEY);

        if (count($recipients) === 1) {
            $this->sendSingle($recipients[0], $base, $override);

            return;
        }

        $this->sendBatch($recipients, $base, $override);
    }

    public function __toString(): string
    {
        return 'mailer';
    }

    /**
     * The real delivery list: To + Cc + Bcc merged into envelope recipients.
     *
     * @return array<int, string>
     */
    private function recipients(SentMessage $message): array
    {
        return array_values(array_map(
            static fn (Address $address): string => $address->getAddress(),
            $message->getEnvelope()->getRecipients(),
        ));
    }

    private function header(Email $email, string $name): ?string
    {
        $header = $email->getHeaders()->get($name);

        if ($header === null) {
            return null;
        }

        return $header->getBodyAsString();
    }

    /**
     * @param array<string, mixed> $base
     */
    private function sendSingle(string $recipient, array $base, ?string $override): void
    {
        $payload = ['to' => $recipient] + $base;

        // Key derived from the full payload (content + this recipient), so two
        // sends of the same content to different recipients get distinct keys
        // while a queue retry of the same send dedupes.
        $key = IdempotencyKey::compute($payload, $this->config, $override);

        try {
            $this->client->send()->email($payload, $key);
        } catch (ValidationException $e) {
            if ($e->getErrorCode() === self::SUPPRESSED_CODE) {
                $this->transportLogger?->warning(
                    'Mailer SDK transport: recipient suppressed; skipping.',
                    ['recipient' => $recipient],
                );

                $this->dispatchSuppressed($recipient, $e->getErrorCode(), $e->getBody());

                return;
            }

            throw new TransportException(
                'Mailer platform rejected the send for '.$recipient.': '.$e->getMessage(),
                0,
                $e,
            );
        } catch (MailerException $e) {
            throw new TransportException(
                'Mailer platform send failed for '.$recipient.': '.$e->getMessage(),
                0,
                $e,
            );
        }
    }

    /**
     * @param array<int, string> $recipients
     * @param array<string, mixed> $base
     */
    private function sendBatch(array $recipients, array $base, ?string $override): void
    {
        // One shared key across the batch, derived from content + the SORTED
        // recipient list, so a requeued job (same list) dedupes while a batch of
        // the same content to a different list gets a distinct key.
        $canonical = $recipients;
        sort($canonical);
        $key = IdempotencyKey::compute(['to' => $canonical] + $base, $this->config, $override);

        $messages = [];

        foreach ($recipients as $recipient) {
            $messages[] = ['to' => $recipient] + $base;
        }

        try {
            $result = $this->client->send()->batch($messages, $key);
        } catch (MailerException $e) {
            throw new TransportException(
                'Mailer platform batch send failed: '.$e->getMessage(),
                0,
                $e,
            );
        }

        $this->handleBatchResult($result, $recipients);
    }

    /**
     * Inspect the per-message batch results: dispatch a suppressed event for
     * each suppressed recipient (not a failure) and raise a TransportException
     * if any recipient hard-failed (quota_exceeded, template_not_found, ...).
     *
     * @param array<int, string> $recipients
     */
    private function handleBatchResult(BatchResult $result, array $recipients): void
    {
        $failures = [];

        foreach ($result->messages as $item) {
            $recipient = $this->recipientForIndex($item->index, $recipients);

            if ($item->status === 'suppressed') {
                $this->transportLogger?->warning(
                    'Mailer SDK transport: recipient suppressed in batch; skipping.',
                    ['recipient' => $recipient],
                );

                $this->dispatchSuppressed($recipient ?? '', $item->code ?? self::SUPPRESSED_CODE);

                continue;
            }

            if ($item->status === 'failed') {
                $label = $recipient ?? ('#'.($item->index ?? '?'));
                $failures[] = $label.' ('.($item->code ?? $item->error ?? 'failed').')';
            }
        }

        if ($failures !== []) {
            throw new TransportException(
                'Mailer platform batch send failed for: '.implode(', ', $failures).'.',
            );
        }
    }

    /**
     * @param array<int, string> $recipients
     */
    private function recipientForIndex(?int $index, array $recipients): ?string
    {
        if ($index === null) {
            return null;
        }

        return $recipients[$index] ?? null;
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function dispatchSuppressed(string $recipient, ?string $reason, ?array $body = null): void
    {
        $this->events?->dispatch(new MessageSuppressed($recipient, $reason, $body));
    }
}
