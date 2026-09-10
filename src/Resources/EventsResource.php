<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\EventOccurrence;
use Recado\Sdk\Dto\Paginated;
use Recado\Sdk\Http\HttpClient;
use Recado\Sdk\Resources\Concerns\PaginatesResults;

/**
 * The Events resource: the read side of `send()->track()`.
 *
 * Occurrences come back newest first. `event` filters by event NAME (as the
 * integration sends it, normalized like on /track); a name the project never
 * recorded is a `404` `event_not_found`. `since`/`until` are inclusive absolute
 * timestamps over `created_at` — a value without an offset is read as UTC, so
 * `until=2026-09-09` cuts at the START of that day.
 */
final readonly class EventsResource
{
    use PaginatesResults;

    public function __construct(private HttpClient $http) {}

    /**
     * List the project's event occurrences (GET /events).
     *
     * @param  array<string, mixed>  $query  event, email, since, until, per_page, page.
     * @return Paginated<EventOccurrence>
     */
    public function list(array $query = []): Paginated
    {
        $response = $this->http->get('events', ['query' => $query]);

        return Paginated::fromArray($response, EventOccurrence::fromArray(...));
    }

    /**
     * Lazily iterate every occurrence across all pages (GET /events).
     *
     * @param  array<string, mixed>  $query  the list() filters (page is managed
     *                                       automatically).
     * @return \Generator<int, EventOccurrence>
     */
    public function cursor(array $query = []): \Generator
    {
        return $this->paginate(
            fn (int $page): Paginated => $this->list(array_merge($query, ['page' => $page])),
        );
    }

    /**
     * List one contact's occurrences (GET /contacts/{email}/events).
     *
     * @param  array<string, mixed>  $query  event, since, until, per_page, page.
     * @return Paginated<EventOccurrence>
     */
    public function forContact(string $email, array $query = []): Paginated
    {
        $response = $this->http->get('contacts/'.rawurlencode($email).'/events', ['query' => $query]);

        return Paginated::fromArray($response, EventOccurrence::fromArray(...));
    }

    /**
     * Lazily iterate every occurrence of one contact across all pages
     * (GET /contacts/{email}/events).
     *
     * @param  array<string, mixed>  $query  event, since, until, per_page.
     * @return \Generator<int, EventOccurrence>
     */
    public function forContactCursor(string $email, array $query = []): \Generator
    {
        return $this->paginate(
            fn (int $page): Paginated => $this->forContact($email, array_merge($query, ['page' => $page])),
        );
    }
}
