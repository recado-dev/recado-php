<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\Paginated;
use Recado\Sdk\Dto\Waitlist;
use Recado\Sdk\Dto\WaitlistMember;
use Recado\Sdk\Http\HttpClient;
use Recado\Sdk\Resources\Concerns\PaginatesResults;

/**
 * The Waitlists resource: hosted pre-launch signup pages with referral
 * positions.
 *
 * Read-only plus the one irreversible action. Authoring stays in the dashboard
 * on purpose — creating a waitlist also creates its dedicated contact list and
 * claims a GLOBALLY unique public slug — so there is no create/update/delete
 * here by design.
 *
 * Permissions reuse the contacts pair (`contacts.view` to read,
 * `contacts.manage` to launch): waitlists are audience tooling, not a
 * permission domain of their own.
 */
final readonly class WaitlistsResource
{
    use PaginatesResults;

    public function __construct(private HttpClient $http) {}

    /**
     * List the project's waitlists (GET /waitlists).
     *
     * `members_count` always comes along. The signup breakdown is opt-in via
     * `include=stats` because it costs aggregates per row — without it,
     * `Waitlist::$stats` is null on every item.
     *
     * @param  array<string, mixed>  $query  status (open|launched), search, sort,
     *                                       include=stats, per_page, page.
     * @return Paginated<Waitlist>
     */
    public function list(array $query = []): Paginated
    {
        $response = $this->http->get('waitlists', ['query' => $query]);

        return Paginated::fromArray($response, Waitlist::fromArray(...));
    }

    /**
     * Lazily iterate every waitlist across all pages (GET /waitlists).
     *
     * @param  array<string, mixed>  $query  the list() filters (page is managed
     *                                       automatically).
     * @return \Generator<int, Waitlist>
     */
    public function cursor(array $query = []): \Generator
    {
        return $this->paginate(
            fn (int $page): Paginated => $this->list(array_merge($query, ['page' => $page])),
        );
    }

    /**
     * Fetch one waitlist (GET /waitlists/{id}), always with its stats.
     *
     * `404` `waitlist_not_found` for an unknown or cross-project id.
     */
    public function get(int $id): Waitlist
    {
        $response = $this->http->get('waitlists/'.$id);

        return Waitlist::fromArray($response['data'] ?? []);
    }

    /**
     * List the signups in RANK order, with their live position
     * (GET /waitlists/{id}/members).
     *
     * Positions are never stored — each listing ranks the whole waitlist with a
     * window function — and the `email` filter is applied AFTER ranking, so a
     * matched member still reports the position it holds on the full list.
     *
     * @param  array<string, mixed>  $query  email (substring), per_page, page.
     * @return Paginated<WaitlistMember>
     */
    public function members(int $id, array $query = []): Paginated
    {
        $response = $this->http->get('waitlists/'.$id.'/members', ['query' => $query]);

        return Paginated::fromArray($response, WaitlistMember::fromArray(...));
    }

    /**
     * Lazily iterate every member across all pages
     * (GET /waitlists/{id}/members).
     *
     * @param  array<string, mixed>  $query  email, per_page (page is managed
     *                                       automatically).
     * @return \Generator<int, WaitlistMember>
     */
    public function membersCursor(int $id, array $query = []): \Generator
    {
        return $this->paginate(
            fn (int $page): Paginated => $this->members($id, array_merge($query, ['page' => $page])),
        );
    }

    /**
     * Launch the waitlist (POST /waitlists/{id}/launch). **Irreversible.**
     *
     * An atomic compare-and-swap claims `open → launched`, the public signup
     * page stops accepting signups, every member contact is tagged
     * `early-adopter` through the normal tag path (so `tag_added` automations
     * fire) and — unless `$createCampaign` is false — a DRAFT announcement
     * campaign is created, pre-targeted at the waitlist's own list, with its id
     * on `Waitlist::$launchCampaignId`.
     *
     * There is no unlaunch, here or in the dashboard. A second launch (and the
     * loser of a concurrent one) is refused with `422`
     * `waitlist_already_launched`.
     */
    public function launch(int $id, bool $createCampaign = true): Waitlist
    {
        $response = $this->http->post('waitlists/'.$id.'/launch', [
            'json' => ['create_campaign' => $createCampaign],
        ]);

        return Waitlist::fromArray($response['data'] ?? []);
    }
}
