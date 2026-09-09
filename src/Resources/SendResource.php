<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\BatchResult;
use Recado\Sdk\Dto\SentMessage;
use Recado\Sdk\Http\HttpClient;

/**
 * The Send resource: transactional sends, batch sends, event tracking and
 * contact subscription.
 */
final readonly class SendResource
{
    public function __construct(private HttpClient $http) {}

    /**
     * Send a single transactional email (POST /send).
     *
     * The payload is passed through as-is, so every /send field works,
     * including `attachments`: an array (max 10) of `{filename, content_type,
     * content}` objects where `content` is standard base64 (limits: 10 MB
     * decoded per file and per send — the latter rejected with a 422 and code
     * `attachments_too_large`; executable filename extensions are refused).
     *
     * Optional send options (all snapshotted on the message at queue time):
     * `cc`/`bcc` (arrays of emails, max 10 each; copies never create
     * contacts and suppressed copy addresses are silently dropped),
     * `reply_to` (single email), `from`/`from_name` (per-send sender
     * override — `from` must be on a verified sending domain when the
     * project enforces it, 422 code `sending_domain_not_verified`),
     * `headers` (max 10 custom `X-*` headers; `X-SES-*`/`X-Recado-*` are
     * reserved) and `metadata` (up to 10 scalar values, 4 KB serialized;
     * exposed and filterable through the messages endpoints).
     *
     * Optional contact fields `first_name`, `last_name` and `name` (split on
     * the first whitespace; the explicit fields win) are applied to the
     * contact the send upserts: set on create, updated when provided, never
     * cleared when omitted.
     *
     * @param  array<string, mixed>  $payload  `to` plus either `template` or
     *                                         `subject`+`body`, optional `text`,
     *                                         `variables`, `attachments`, `cc`,
     *                                         `bcc`, `reply_to`, `from`,
     *                                         `from_name`, `headers`, `metadata`,
     *                                         `first_name`, `last_name`, `name`.
     */
    public function email(array $payload, ?string $idempotencyKey = null): SentMessage
    {
        $options = ['json' => $payload];

        if ($idempotencyKey !== null) {
            $options['idempotency_key'] = $idempotencyKey;
        }

        $response = $this->http->post('send', $options);

        return SentMessage::fromArray($response['data'] ?? []);
    }

    /**
     * Send a batch of transactional emails (POST /send/batch).
     *
     * Each item carries the same fields as {@see email()}, including the
     * send options (`cc`, `bcc`, `reply_to`, `from`, `from_name`, `headers`,
     * `metadata`) and the contact fields (`first_name`, `last_name`, `name`); an item whose `from` override is not on a verified
     * sending domain fails per item with code `sending_domain_not_verified`.
     * The batch endpoint rejects `attachments` on any message (422) — the
     * field is single-send only; use {@see email()} per recipient instead
     * (the Laravel mail transport does that fan-out automatically).
     *
     * @param  array<int, array<string, mixed>>  $messages  1-100 message payloads.
     */
    public function batch(array $messages, ?string $idempotencyKey = null): BatchResult
    {
        $options = ['json' => ['messages' => array_values($messages)]];

        if ($idempotencyKey !== null) {
            $options['idempotency_key'] = $idempotencyKey;
        }

        $response = $this->http->post('send/batch', $options);

        return BatchResult::fromArray($response['data'] ?? []);
    }

    /**
     * Record an event occurrence for a contact (POST /track).
     *
     * `$contact` carries the optional TOP-LEVEL contact fields the endpoint
     * accepts next to the event — `first_name`, `last_name`, `name` (split on
     * the first whitespace; the explicit fields win) and `locale` — which the
     * platform applies to the contact the event upserts: set on create,
     * updated when provided, never cleared when omitted. It is deliberately a
     * pass-through array (like every other payload in this SDK). Do NOT put
     * event payload data here: that is `$data`.
     *
     * It also carries `lists` (ids of the project's lists, max 50) and `tags`
     * (names, max 25, created on first use). The platform attaches both to the
     * contact BEFORE it records the occurrence, so an event-triggered
     * automation already observes them (a `has_tag` condition can match a tag
     * sent with this very call). Both are idempotent and neither changes the
     * contact's subscription status; an unknown list id is rejected with a
     * `422` `ValidationException` carrying the code `list_not_found`.
     *
     * The positional `$event`/`$email` (and the `data` block) always win: a
     * `$contact` entry with one of those keys is ignored, so the array can
     * never redirect the call to another contact or event.
     *
     * @param  array<string, mixed>  $data  Optional event payload.
     * @param  array<string, mixed>  $contact  Optional contact fields:
     *                                         `first_name`, `last_name`,
     *                                         `name`, `locale`, `lists`,
     *                                         `tags`.
     * @return array<string, mixed> The `data` block: id, event, email.
     */
    public function track(string $event, string $email, array $data = [], array $contact = []): array
    {
        $payload = ['event' => $event, 'email' => $email];

        if ($data !== []) {
            $payload['data'] = $data;
        }

        // `+=` keeps the keys already on the payload, so `event`/`email`/`data`
        // can never be shadowed by a contact field; an empty array leaves the
        // payload byte-identical to a pre-2.4 track() call.
        $payload += $contact;

        $response = $this->http->post('track', ['json' => $payload]);

        return $response['data'] ?? [];
    }

    /**
     * Subscribe a contact (POST /contacts/subscribe).
     *
     * @param  array<string, mixed>  $payload  `email` plus optional first_name,
     *                                         last_name, locale, attributes,
     *                                         lists, tags.
     * @return array<string, mixed> The `data` block: id, email, status.
     */
    public function subscribe(array $payload): array
    {
        $response = $this->http->post('contacts/subscribe', ['json' => $payload]);

        return $response['data'] ?? [];
    }
}
