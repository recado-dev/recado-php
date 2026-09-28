<?php

declare(strict_types=1);

namespace Recado\Sdk\Webhooks;

use LogicException;
use Recado\Sdk\Exception\WebhookVerificationException;

/**
 * The envelope every Recado webhook delivery carries:
 * `{event, timestamp, project: {uuid}, sandbox, data}`.
 *
 * `data` is kept as the raw decoded array so every event stays readable,
 * including events newer than this SDK. `message.replied` also has a typed
 * view through `messageReplied()`.
 *
 * `sandbox` is true when the event came from the project's sandbox twin (a
 * test run, nothing reached a real recipient).
 */
final readonly class WebhookPayload
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $raw  The whole decoded body.
     */
    public function __construct(
        public string $event,
        public ?string $timestamp,
        public ?string $projectUuid,
        public bool $sandbox,
        public array $data,
        public array $raw,
    ) {}

    /**
     * Verify the signature of a raw delivery body, then parse it.
     *
     * @throws WebhookVerificationException
     */
    public static function constructEvent(string $payload, ?string $signature, string $secret): self
    {
        WebhookSignature::verify($payload, $signature, $secret);

        return self::fromJson($payload);
    }

    /**
     * Parse a raw delivery body WITHOUT verifying it. Only use this on a body
     * whose signature was already checked (see `WebhookSignature`).
     *
     * @throws WebhookVerificationException `invalid_webhook_payload`
     */
    public static function fromJson(string $payload): self
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw WebhookVerificationException::invalidPayload('the body is not a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return self::fromArray($decoded);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws WebhookVerificationException `invalid_webhook_payload`
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['event']) || ! is_string($data['event']) || $data['event'] === '') {
            throw WebhookVerificationException::invalidPayload('the `event` key is missing.');
        }

        $project = is_array($data['project'] ?? null) ? $data['project'] : [];

        return new self(
            event: $data['event'],
            timestamp: isset($data['timestamp']) ? (string) $data['timestamp'] : null,
            projectUuid: isset($project['uuid']) ? (string) $project['uuid'] : null,
            sandbox: (bool) ($data['sandbox'] ?? false),
            data: is_array($data['data'] ?? null) ? $data['data'] : [],
            raw: $data,
        );
    }

    /**
     * The event as an enum case, or null for `ping` and for events newer than
     * this SDK.
     */
    public function type(): ?WebhookEvent
    {
        return WebhookEvent::tryFrom($this->event);
    }

    /**
     * Whether this delivery is the given event.
     */
    public function is(WebhookEvent|string $event): bool
    {
        return $this->event === ($event instanceof WebhookEvent ? $event->value : $event);
    }

    /**
     * The typed `message.replied` data.
     *
     * @throws LogicException when this delivery is another event.
     */
    public function messageReplied(): MessageReplied
    {
        if (! $this->is(WebhookEvent::MessageReplied)) {
            throw new LogicException(sprintf('This webhook is `%s`, not `message.replied`.', $this->event));
        }

        return MessageReplied::fromArray($this->data);
    }
}
