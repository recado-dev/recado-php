<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\Campaign;
use Recado\Sdk\Dto\CampaignPreview;
use Recado\Sdk\Dto\CampaignReadiness;
use Recado\Sdk\Dto\CampaignStats;
use Recado\Sdk\Dto\Paginated;
use Recado\Sdk\Exception\CampaignSendNotConfirmedException;
use Recado\Sdk\Http\HttpClient;
use Recado\Sdk\Resources\Concerns\PaginatesResults;

/**
 * The Campaigns resource: the full newsletter lifecycle — create and edit a
 * draft, inspect it (preview, readiness, recipient count, stats), then send,
 * schedule, unschedule or cancel it.
 *
 * **Send safety.** The resource is no longer read-only, so the guard is
 * explicit at the call site instead of implicit in what the SDK omits:
 * `send($id, confirm: true)` is the only way to start a send. Without that
 * argument the call throws `CampaignSendNotConfirmedException` BEFORE any HTTP
 * request is made — a stray `send()` can never reach the audience. Every other
 * method (schedule included, since it only arms a future send that `cancel()`
 * or `unschedule()` can still stop) needs no confirmation.
 *
 * Failures keep the API's machine codes: `ValidationException::getErrorCode()`
 * returns `not_sendable`, `missing_subject`, `missing_content`,
 * `no_recipients`, `sending_domain_not_verified`, `quota_exceeded`,
 * `ab_invalid_variants`, `ab_test_locked`, `ab_test_requires_plan`,
 * `campaign_not_editable`, `campaign_not_cancellable`,
 * `campaign_not_deletable` and friends, untranslated.
 */
final readonly class CampaignsResource
{
    use PaginatesResults;

    public function __construct(private HttpClient $http) {}

    /**
     * List campaigns (GET /campaigns).
     *
     * @param  array<string, mixed>  $query  status (string or array), search,
     *                                       scheduled_from, scheduled_to, sort
     *                                       (`created_at`, `scheduled_at`, `name`,
     *                                       each optionally `-` prefixed),
     *                                       include (`stats`), per_page, page.
     * @return Paginated<Campaign>
     */
    public function list(array $query = []): Paginated
    {
        $response = $this->http->get('campaigns', ['query' => $query]);

        return Paginated::fromArray($response, Campaign::fromArray(...));
    }

    /**
     * Lazily iterate every campaign across all pages (GET /campaigns).
     *
     * @param  array<string, mixed>  $query  the list() filters (page is managed
     *                                       automatically).
     * @return \Generator<int, Campaign>
     */
    public function cursor(array $query = []): \Generator
    {
        return $this->paginate(
            fn (int $page): Paginated => $this->list(array_merge($query, ['page' => $page])),
        );
    }

    /**
     * Fetch a single campaign with its engagement stats (GET /campaigns/{id}).
     *
     * @param  array<string, mixed>  $query  include (`top_links`, `variants`, or
     *                                       both as a comma-separated string).
     */
    public function get(int $id, array $query = []): Campaign
    {
        $options = $query === [] ? [] : ['query' => $query];

        $response = $this->http->get('campaigns/'.$id, $options);

        return Campaign::fromArray($response['data'] ?? []);
    }

    /**
     * Batch engagement stats for campaigns a client already holds
     * (GET /campaigns/stats?ids[]=...), resolved in one aggregate query.
     *
     * Ids outside the project are simply absent from the result — the endpoint
     * never reveals whether a foreign id exists.
     *
     * @param  array<int, int>  $ids  1..100 campaign ids.
     * @return array<int, CampaignStats> Keyed by campaign id.
     */
    public function stats(array $ids): array
    {
        $response = $this->http->get('campaigns/stats', [
            'query' => ['ids' => array_values($ids)],
        ]);

        $stats = [];

        foreach ($response['data'] ?? [] as $id => $payload) {
            if (is_array($payload)) {
                $stats[(int) $id] = CampaignStats::fromArray($payload);
            }
        }

        return $stats;
    }

    /**
     * Create a draft campaign (POST /campaigns).
     *
     * @param  array<string, mixed>  $payload  name (required), subject, preheader,
     *                                         from_name, from_email, editor
     *                                         (`blocks`|`html`|`markdown`,
     *                                         immutable afterwards), content,
     *                                         lists, segments, plus the A/B
     *                                         pair below.
     *
     * **A/B testing.** `ab_test` carries the configuration
     * (`enabled` — the only required key —, `test_fraction` 0.1..0.5,
     * `winner_metric` `opens`|`clicks`, `test_duration_minutes` 30..2880,
     * defaulting to 0.2 / opens / 240) and `variants` the 2..4 alternatives:
     * `subject`, `preheader`, `from_name`, `from_email` and `content`, each
     * optional and each inheriting the campaign's own field when null. The
     * whole set is replaced on every write and the labels A..D are assigned
     * server-side in array order, so a `label` you pass is ignored. The
     * campaign's `editor` also governs the variant content shape — a variant
     * never has an editor of its own. Without the A/B plan feature, enabling
     * the test is a `422` (`ab_test_requires_plan`).
     */
    public function create(array $payload): Campaign
    {
        $response = $this->http->post('campaigns', ['json' => $payload]);

        return Campaign::fromArray($response['data'] ?? []);
    }

    /**
     * Update a draft campaign (PATCH /campaigns/{id}).
     *
     * Only drafts are editable (`422` + `campaign_not_editable` otherwise) and
     * `editor` can never be changed. Targeting is only touched when the
     * `lists`/`segments` key is present; pass `[]` to clear it.
     *
     * Variants follow the same whole-set replacement as create(): send
     * `variants` to add, edit or remove them, and `ab_test.enabled = false`
     * to turn the test off (which clears them). Once the test group has gone
     * out the variants are frozen — `$campaign->abTest->locked` says so, and
     * a write touching them is a `422` (`ab_test_locked`).
     *
     * @param  array<string, mixed>  $payload
     */
    public function update(int $id, array $payload): Campaign
    {
        $response = $this->http->patch('campaigns/'.$id, ['json' => $payload]);

        return Campaign::fromArray($response['data'] ?? []);
    }

    /**
     * Delete a campaign (DELETE /campaigns/{id}).
     *
     * Restricted to campaigns that never reached the audience — `draft`,
     * `cancelled` and `failed`; anything else is `422` +
     * `campaign_not_deletable` (unschedule or cancel it first).
     */
    public function delete(int $id): void
    {
        $this->http->delete('campaigns/'.$id);
    }

    /**
     * Copy a campaign into a fresh draft (POST /campaigns/{id}/duplicate).
     *
     * Allowed from any status, `sent` included — re-issuing last week's
     * newsletter is the point. The source is left untouched.
     *
     * @param  string|null  $name  Defaults to the source name with a " (copy)" suffix.
     */
    public function duplicate(int $id, ?string $name = null): Campaign
    {
        $options = $name === null ? [] : ['json' => ['name' => $name]];

        $response = $this->http->post('campaigns/'.$id.'/duplicate', $options);

        return Campaign::fromArray($response['data'] ?? []);
    }

    /**
     * Start sending a campaign immediately (POST /campaigns/{id}/send).
     *
     * This fires real mail at a real audience and cannot be recalled once the
     * batch is queued, so the intent must be explicit: without `confirm: true`
     * the call throws `CampaignSendNotConfirmedException` before any request is
     * made. Use `readiness()` to check the pre-flight without sending.
     *
     * The send is idempotent under races, not under success: a second send
     * after a successful one loses the atomic status claim and returns `422`
     * `not_sendable`.
     *
     * @throws CampaignSendNotConfirmedException when $confirm is not true.
     */
    public function send(int|string $id, bool $confirm = false): Campaign
    {
        if ($confirm !== true) {
            throw CampaignSendNotConfirmedException::forCampaign($id);
        }

        $response = $this->http->post('campaigns/'.$id.'/send');

        return Campaign::fromArray($response['data'] ?? []);
    }

    /**
     * Schedule a future send (POST /campaigns/{id}/schedule).
     *
     * Runs the send pre-flight except the recipient count (the audience is
     * resolved at send time).
     *
     * @param  string  $scheduledAt  ISO 8601 timestamp, must be in the future.
     */
    public function schedule(int $id, string $scheduledAt): Campaign
    {
        $response = $this->http->post('campaigns/'.$id.'/schedule', [
            'json' => ['scheduled_at' => $scheduledAt],
        ]);

        return Campaign::fromArray($response['data'] ?? []);
    }

    /**
     * Unschedule a scheduled campaign, back to draft
     * (DELETE /campaigns/{id}/schedule). A campaign that is not scheduled is
     * `422` + `campaign_not_scheduled`.
     */
    public function unschedule(int $id): Campaign
    {
        $response = $this->http->delete('campaigns/'.$id.'/schedule');

        return Campaign::fromArray($response['data'] ?? []);
    }

    /**
     * Cancel a `scheduled` or `sending` campaign (POST /campaigns/{id}/cancel).
     *
     * Not the same as unschedule: the campaign ends `cancelled`, not `draft`.
     * From `sending` the dispatch batch stops at its next chunk; messages
     * already handed to the queue cannot be recalled, so the counters keep
     * whatever they reached. A campaign in any other status is `422` +
     * `campaign_not_cancellable`.
     */
    public function cancel(int $id): Campaign
    {
        $response = $this->http->post('campaigns/'.$id.'/cancel');

        return Campaign::fromArray($response['data'] ?? []);
    }

    /**
     * Send a proof of the campaign to up to 5 arbitrary addresses
     * (POST /campaigns/{id}/test-send).
     *
     * A proof carries no campaign id, so it never touches the campaign status,
     * its counters or its stats. Suppressed addresses are skipped rather than
     * aborting the proof for the others; every address suppressed is `422` +
     * `recipient_suppressed`.
     *
     * @param  array<int, string>  $emails  1..5 addresses.
     * @param  string|null  $contactEmail  An existing contact of the project whose
     *                                     data seeds the placeholders (and whose
     *                                     real unsubscribe/preferences links are
     *                                     rendered). Unknown → `404`
     *                                     `contact_not_found`.
     * @param  int|null  $variant  An A/B variant id of this campaign.
     * @return array<int, string> The addresses actually queued (`sent_to`).
     */
    public function testSend(int $id, array $emails, ?string $contactEmail = null, ?int $variant = null): array
    {
        $payload = ['emails' => array_values($emails)];

        if ($contactEmail !== null) {
            $payload['contact_email'] = $contactEmail;
        }

        if ($variant !== null) {
            $payload['variant'] = $variant;
        }

        $response = $this->http->post('campaigns/'.$id.'/test-send', ['json' => $payload]);

        $sentTo = [];

        foreach ($response['data']['sent_to'] ?? [] as $email) {
            if (is_scalar($email)) {
                $sentTo[] = (string) $email;
            }
        }

        return $sentTo;
    }

    /**
     * Render the campaign exactly as a send would, without sending
     * (POST /campaigns/{id}/preview).
     *
     * Side-effect free and available in any status: no message row, no event,
     * no counter change, no mail.
     *
     * @param  string|null  $contactEmail  A contact of this project whose data feeds
     *                                     the `{{ contact.* }}` placeholders;
     *                                     omitted → neutral sample data.
     * @param  int|null  $variant  An A/B variant id of this campaign.
     */
    public function preview(int $id, ?string $contactEmail = null, ?int $variant = null): CampaignPreview
    {
        $payload = [];

        if ($contactEmail !== null) {
            $payload['contact_email'] = $contactEmail;
        }

        if ($variant !== null) {
            $payload['variant'] = $variant;
        }

        // No body at all rather than an empty JSON array when nothing was given:
        // both fields are optional, so a bare preview is a bodyless POST.
        $response = $this->http->post(
            'campaigns/'.$id.'/preview',
            $payload === [] ? [] : ['json' => $payload],
        );

        return CampaignPreview::fromArray($response['data'] ?? []);
    }

    /**
     * The pre-send checklist (GET /campaigns/{id}/readiness): the same
     * pre-flight a send runs, without starting anything.
     */
    public function readiness(int $id): CampaignReadiness
    {
        $response = $this->http->get('campaigns/'.$id.'/readiness');

        return CampaignReadiness::fromArray($response['data'] ?? []);
    }

    /**
     * How many contacts a lists/segments selection reaches
     * (GET /campaigns/recipient-count), before creating or sending anything.
     *
     * The count is the query a send uses: distinct subscribed contacts across
     * the union of the selection, minus the suppressions of the project.
     *
     * @param  array<int, int>  $lists  List ids (at least one of lists/segments
     *                                  must be non-empty).
     * @param  array<int, int>  $segments  Segment ids.
     * @param  bool|null  $premium  Restrict to paid subscribers, mirroring a
     *                              premium campaign.
     * @return int `recipients_total`.
     */
    public function recipientCount(array $lists = [], array $segments = [], ?bool $premium = null): int
    {
        $query = [];

        if ($lists !== []) {
            $query['lists'] = array_values($lists);
        }

        if ($segments !== []) {
            $query['segments'] = array_values($segments);
        }

        if ($premium !== null) {
            $query['premium'] = $premium ? '1' : '0';
        }

        $response = $this->http->get('campaigns/recipient-count', ['query' => $query]);

        return (int) ($response['data']['recipients_total'] ?? 0);
    }
}
