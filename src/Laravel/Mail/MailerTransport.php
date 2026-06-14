<?php

declare(strict_types=1);

namespace Mailer\Sdk\Laravel\Mail;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use Mailer\Sdk\Dto\BatchResult;
use Mailer\Sdk\Exception\MailerException;
use Mailer\Sdk\Exception\UnsupportedFeatureException;
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

        $base = $this->basePayload($email);

        $key = $this->idempotencyKey($email, $base);

        if (count($recipients) === 1) {
            $this->sendSingle($recipients[0], $base, $key);

            return;
        }

        $this->sendBatch($recipients, $base, $key);
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

    /**
     * Build the content payload shared by every recipient (the `to` key is
     * filled in per recipient at send time). Either a template payload (when
     * the X-Mailer-Template header is present) or an inline subject/body one.
     *
     * @return array<string, mixed>
     */
    private function basePayload(Email $email): array
    {
        $this->guardAttachments($email);
        $this->warnIfFromIsSet($email);

        $template = $this->header($email, MailerHeaders::TEMPLATE);

        if ($template !== null) {
            return [
                'template' => $template,
                'variables' => $this->variables($email),
            ];
        }

        $html = $email->getHtmlBody();
        $text = $email->getTextBody();

        $payload = [
            'subject' => (string) $email->getSubject(),
            // The API requires `body`; fall back to the text body when the
            // message is text-only so a plain Mail::raw() still goes out.
            'body' => $this->stringBody($html) ?? $this->stringBody($text) ?? '',
        ];

        $textBody = $this->stringBody($text);

        if ($textBody !== null) {
            $payload['text'] = $textBody;
        }

        $variables = $this->variables($email);

        if ($variables !== []) {
            $payload['variables'] = $variables;
        }

        return $payload;
    }

    /**
     * Symfony body parts can be strings or resources; normalize to a string.
     */
    private function stringBody(mixed $body): ?string
    {
        if ($body === null) {
            return null;
        }

        if (is_resource($body)) {
            $contents = stream_get_contents($body);

            return $contents === false ? null : $contents;
        }

        return (string) $body;
    }

    private function guardAttachments(Email $email): void
    {
        if ($email->getAttachments() === []) {
            return;
        }

        $mode = (string) ($this->config['attachments'] ?? 'fail');

        if ($mode === 'ignore') {
            $this->transportLogger?->warning(
                'Mailer SDK transport: dropping attachments — the platform /send API does not support them.',
            );

            return;
        }

        throw new UnsupportedFeatureException(
            'The Mailer platform /send API does not support attachments. '
            .'Remove the attachment from the Mailable, or set mailer-sdk.mail.attachments to "ignore" '
            .'to send the message without it.',
        );
    }

    private function warnIfFromIsSet(Email $email): void
    {
        if ($email->getFrom() !== []) {
            $this->transportLogger?->debug(
                'Mailer SDK transport: ignoring the message From address; the platform uses the '
                ."project's configured sender.",
            );
        }
    }

    /**
     * Decode the X-Mailer-Variables JSON header into an associative array.
     *
     * @return array<string, mixed>
     */
    private function variables(Email $email): array
    {
        $raw = $this->header($email, MailerHeaders::VARIABLES);

        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
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
     * Resolve the idempotency key for this send. An explicit header wins;
     * otherwise the configured strategy decides (content hash / random / off).
     *
     * @param array<string, mixed> $payload
     */
    private function idempotencyKey(Email $email, array $payload): ?string
    {
        $explicit = $this->header($email, MailerHeaders::IDEMPOTENCY_KEY);

        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }

        $strategy = (string) ($this->config['idempotency'] ?? 'content');

        return match ($strategy) {
            'random' => Str::uuid()->toString(),
            'off' => null,
            default => $this->contentKey($payload),
        };
    }

    /**
     * Deterministic key derived from the canonical content, so a queue retry of
     * the same job never duplicates the send.
     *
     * @param array<string, mixed> $payload
     */
    private function contentKey(array $payload): string
    {
        $canonical = [
            'subject' => $payload['subject'] ?? null,
            'body' => $payload['body'] ?? null,
            'text' => $payload['text'] ?? null,
            'template' => $payload['template'] ?? null,
            'variables' => $payload['variables'] ?? null,
        ];

        ksort($canonical);

        $hash = hash('sha256', (string) json_encode($canonical));

        // Prefix + truncate well under the API's 255-char idempotency key limit.
        return 'txn_'.substr($hash, 0, 60);
    }

    /**
     * @param array<string, mixed> $base
     */
    private function sendSingle(string $recipient, array $base, ?string $key): void
    {
        $payload = ['to' => $recipient] + $base;

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
    private function sendBatch(array $recipients, array $base, ?string $key): void
    {
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
