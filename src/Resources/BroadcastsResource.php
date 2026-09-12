<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\Broadcast;
use Recado\Sdk\Dto\BroadcastRecipientCounts;
use Recado\Sdk\Dto\Paginated;
use Recado\Sdk\Exception\CampaignSendNotConfirmedException;
use Recado\Sdk\Http\HttpClient;
use Recado\Sdk\Resources\Concerns\PaginatesResults;

/**
 * The Broadcasts resource: the mass in-app / push notification lifecycle —
 * the notification sibling of {@see CampaignsResource}. Email is deliberately
 * not a broadcast channel; mass email is what campaigns are for.
 *
 * The lifecycle mirrors campaigns field for field (`draft` → `scheduled` →
 * `sending` → `sent`, plus `cancelled` / `failed`), so a client that already
 * drives campaigns drives broadcasts with the same code.
 *
 * **Send safety.** Like a campaign send, `send($id, confirm: true)` is the only
 * way to start one. Without that argument the call throws
 * `CampaignSendNotConfirmedException` BEFORE any HTTP request is made — a stray
 * `send()` can never reach the audience. `schedule()` needs no confirmation: it
 * only arms a future send that `unschedule()` or `cancel()` can still stop.
 *
 * Failures keep the API's machine codes on
 * `ValidationException::getErrorCode()`: `not_sendable`, `missing_content`,
 * `no_channels`, `no_recipients`, `push_not_entitled`, `push_not_configured`,
 * `quota_exceeded`, `broadcast_not_editable`, `broadcast_not_scheduled`,
 * `broadcast_not_cancellable`, `recipient_blocked`.
 */
final readonly class BroadcastsResource
{
    use PaginatesResults;

    public function __construct(private HttpClient $http) {}

    /**
     * List broadcasts (GET /broadcasts).
     *
     * @param  array<string, mixed>  $query  status (string or array), search
     *                                       (name/title substring),
     *                                       scheduled_from, scheduled_to, sort,
     *                                       include (`stats`), per_page, page.
     * @return Paginated<Broadcast>
     */
    public function list(array $query = []): Paginated
    {
        $response = $this->http->get('broadcasts', ['query' => $query]);

        return Paginated::fromArray($response, Broadcast::fromArray(...));
    }

    /**
     * Lazily iterate every broadcast across all pages (GET /broadcasts).
     *
     * @param  array<string, mixed>  $query  the list() filters (page is managed
     *                                       automatically).
     * @return \Generator<int, Broadcast>
     */
    public function cursor(array $query = []): \Generator
    {
        return $this->paginate(
            fn (int $page): Paginated => $this->list(array_merge($query, ['page' => $page])),
        );
    }

    /**
     * Fetch a single broadcast with its stats (GET /broadcasts/{id}).
     */
    public function get(int $id): Broadcast
    {
        $response = $this->http->get('broadcasts/'.$id);

        return Broadcast::fromArray($response['data'] ?? []);
    }

    /**
     * Create a draft broadcast (POST /broadcasts).
     *
     * `name` is required and internal only. `title`, `body` and `channels`
     * (`in_app` and/or `push` — `email` is rejected) may stay empty while
     * drafting; they are enforced when the broadcast is started, exactly like a
     * campaign's subject and content. `action_url` accepts an absolute http(s)
     * URL or a custom-scheme deep link (`myapp://home`), never a
     * script-executing scheme.
     *
     * @param  array<string, mixed>  $payload  name (required), title, body,
     *                                         action_url, icon, channels, lists,
     *                                         segments.
     */
    public function create(array $payload): Broadcast
    {
        $response = $this->http->post('broadcasts', ['json' => $payload]);

        return Broadcast::fromArray($response['data'] ?? []);
    }

    /**
     * Update a draft broadcast (PATCH /broadcasts/{id}).
     *
     * Only drafts are editable (`422` + `broadcast_not_editable` otherwise —
     * unschedule a scheduled broadcast first). Targeting is only touched when
     * the `lists`/`segments` key is present; pass `[]` to clear it.
     *
     * @param  array<string, mixed>  $payload
     */
    public function update(int $id, array $payload): Broadcast
    {
        $response = $this->http->patch('broadcasts/'.$id, ['json' => $payload]);

        return Broadcast::fromArray($response['data'] ?? []);
    }

    /**
     * Delete a broadcast that is not currently `sending`
     * (DELETE /broadcasts/{id}).
     */
    public function delete(int $id): void
    {
        $this->http->delete('broadcasts/'.$id);
    }

    /**
     * Start sending a broadcast immediately (POST /broadcasts/{id}/send).
     *
     * This fires real notifications at a real audience and cannot be recalled
     * once the batch is queued, so the intent must be explicit: without
     * `confirm: true` the call throws `CampaignSendNotConfirmedException` before
     * any request is made.
     *
     * The send is idempotent under races, not under success: a second send after
     * a successful one loses the atomic status claim and returns `422`
     * `not_sendable`.
     *
     * @throws CampaignSendNotConfirmedException when $confirm is not true.
     */
    public function send(int $id, bool $confirm = false): Broadcast
    {
        if ($confirm !== true) {
            throw CampaignSendNotConfirmedException::forBroadcast($id);
        }

        $response = $this->http->post('broadcasts/'.$id.'/send');

        return Broadcast::fromArray($response['data'] ?? []);
    }

    /**
     * Schedule a future send (POST /broadcasts/{id}/schedule).
     *
     * Runs the same pre-flight as an immediate send EXCEPT the audience and the
     * quota, which are resolved at send time — a momentarily empty audience
     * does not block scheduling.
     *
     * @param  string  $scheduledAt  ISO 8601 timestamp, must be in the future.
     */
    public function schedule(int $id, string $scheduledAt): Broadcast
    {
        $response = $this->http->post('broadcasts/'.$id.'/schedule', [
            'json' => ['scheduled_at' => $scheduledAt],
        ]);

        return Broadcast::fromArray($response['data'] ?? []);
    }

    /**
     * Unschedule a scheduled broadcast, back to draft
     * (DELETE /broadcasts/{id}/schedule). One that is not scheduled is `422` +
     * `broadcast_not_scheduled`.
     */
    public function unschedule(int $id): Broadcast
    {
        $response = $this->http->delete('broadcasts/'.$id.'/schedule');

        return Broadcast::fromArray($response['data'] ?? []);
    }

    /**
     * Cancel a `scheduled` or `sending` broadcast
     * (POST /broadcasts/{id}/cancel).
     *
     * Not the same as unschedule: the broadcast ends `cancelled`, not `draft`.
     * From `sending` the dispatch batch stops at its next chunk; notifications
     * already handed to the queue still go out, so the counters keep whatever
     * they reached.
     */
    public function cancel(int $id): Broadcast
    {
        $response = $this->http->post('broadcasts/'.$id.'/cancel');

        return Broadcast::fromArray($response['data'] ?? []);
    }

    /**
     * Send a proof to ONE existing contact of the project
     * (POST /broadcasts/{id}/test-send).
     *
     * Unlike a campaign proof (arbitrary addresses), the recipient must already
     * be a contact: push needs a registered device and the in-app feed is served
     * per contact, so an unknown address could only produce a blocked send. The
     * proof carries no broadcast id, so the counters and stats stay untouched.
     *
     * @return array<int, string> The channels the proof was queued on.
     */
    public function testSend(int $id, string $email): array
    {
        $response = $this->http->post('broadcasts/'.$id.'/test-send', [
            'json' => ['to' => $email],
        ]);

        $channels = [];

        foreach ($response['data']['channels'] ?? [] as $channel) {
            if (is_scalar($channel)) {
                $channels[] = (string) $channel;
            }
        }

        return $channels;
    }

    /**
     * How many contacts a lists/segments selection reaches PER CHANNEL
     * (GET /broadcasts/recipient-count), before creating or sending anything.
     *
     * At least one of lists/segments is required. Ids of another project are a
     * `422` + `list_not_found` / `segment_not_found`.
     *
     * @param  array<int, int>  $lists
     * @param  array<int, int>  $segments
     */
    public function recipientCount(array $lists = [], array $segments = []): BroadcastRecipientCounts
    {
        $query = [];

        if ($lists !== []) {
            $query['lists'] = array_values($lists);
        }

        if ($segments !== []) {
            $query['segments'] = array_values($segments);
        }

        $response = $this->http->get('broadcasts/recipient-count', ['query' => $query]);

        return BroadcastRecipientCounts::fromArray($response['data'] ?? []);
    }
}
