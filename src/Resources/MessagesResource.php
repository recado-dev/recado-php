<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\Message;
use Recado\Sdk\Dto\Paginated;
use Recado\Sdk\Http\HttpClient;
use Recado\Sdk\Resources\Concerns\PaginatesResults;

/**
 * The Messages resource: read the sending log, and resend one message.
 */
final readonly class MessagesResource
{
    use PaginatesResults;

    public function __construct(private HttpClient $http) {}

    /**
     * List messages (GET /messages).
     *
     * @param  array<string, mixed>  $query  status, source, campaign_id, search,
     *                                       metadata_key + metadata_value (a
     *                                       single exact-match pair, both
     *                                       required together), per_page, page.
     * @return Paginated<Message>
     */
    public function list(array $query = []): Paginated
    {
        $response = $this->http->get('messages', ['query' => $query]);

        return Paginated::fromArray($response, Message::fromArray(...));
    }

    /**
     * Lazily iterate every message across all pages (GET /messages).
     *
     * @param  array<string, mixed>  $query  status, source, campaign_id, search,
     *                                       metadata_key + metadata_value,
     *                                       per_page (page is managed automatically).
     * @return \Generator<int, Message>
     */
    public function cursor(array $query = []): \Generator
    {
        return $this->paginate(
            fn (int $page): Paginated => $this->list(array_merge($query, ['page' => $page])),
        );
    }

    /**
     * Fetch a single message with its event timeline (GET /messages/{uuid}).
     */
    public function get(string $uuid): Message
    {
        $response = $this->http->get('messages/'.rawurlencode($uuid));

        return Message::fromArray($response['data'] ?? []);
    }

    /**
     * Queue a NEW message copying the original's recipient and rendered content
     * (POST /messages/{uuid}/resend) — the end of the support flow that starts
     * with `get()`: the customer says the email never arrived, so you send it
     * again.
     *
     * The copy goes through the regular send path, so every send-time check
     * re-runs: suppression, the plan quota (or the sandbox daily cap) and
     * warm-up consumption. The original row is never touched. Deliberately NOT
     * copied: the template and automation references (a resend is a one-off
     * redelivery, not a re-execution) and attachments (their binaries are
     * deleted at the terminal outcome of the original send).
     *
     * Failures keep their machine code on `ValidationException::getErrorCode()`:
     * `message_not_resendable` (a campaign send, a click-tracked message — its
     * links point at the ORIGINAL message's tracking URLs —, a non-email
     * message, or one still `queued`), `contact_not_found`,
     * `sending_domain_not_verified`, `recipient_suppressed`, `quota_exceeded`
     * and `sandbox_cap_exceeded`.
     *
     * @return Message The NEW message, queued.
     */
    public function resend(string $uuid): Message
    {
        $response = $this->http->post('messages/'.rawurlencode($uuid).'/resend');

        return Message::fromArray($response['data'] ?? []);
    }
}
