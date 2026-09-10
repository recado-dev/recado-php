<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\Paginated;
use Recado\Sdk\Dto\Segment;
use Recado\Sdk\Http\HttpClient;
use Recado\Sdk\Resources\Concerns\PaginatesResults;

/**
 * The Segments resource: the saved queries campaigns target.
 *
 * The condition tree is passed through as a plain array exactly as the API
 * documents it — the SDK deliberately ships no query DSL, so a schema change
 * on the platform never needs an SDK release.
 */
final readonly class SegmentsResource
{
    use PaginatesResults;

    public function __construct(private HttpClient $http) {}

    /**
     * List segments, newest first (GET /segments).
     *
     * List items carry no `contacts_count` (counting runs the compiled query
     * per segment); use get() for a live count.
     *
     * @param  array<string, mixed>  $query  per_page, page.
     * @return Paginated<Segment>
     */
    public function list(array $query = []): Paginated
    {
        $response = $this->http->get('segments', ['query' => $query]);

        return Paginated::fromArray($response, Segment::fromArray(...));
    }

    /**
     * Lazily iterate every segment across all pages (GET /segments).
     *
     * @param  array<string, mixed>  $query  per_page (page is managed automatically).
     * @return \Generator<int, Segment>
     */
    public function cursor(array $query = []): \Generator
    {
        return $this->paginate(
            fn (int $page): Paginated => $this->list(array_merge($query, ['page' => $page])),
        );
    }

    /**
     * Fetch a single segment with a live `contacts_count` (GET /segments/{id}).
     */
    public function get(int $id): Segment
    {
        $response = $this->http->get('segments/'.$id);

        return Segment::fromArray($response['data'] ?? []);
    }

    /**
     * Create a segment (POST /segments).
     *
     * @param  array<string, mixed>  $conditions  `{match: all|any, conditions: [...]}`,
     *                                            one optional nested group level.
     */
    public function create(string $name, array $conditions): Segment
    {
        $response = $this->http->post('segments', [
            'json' => ['name' => $name, 'conditions' => $conditions],
        ]);

        return Segment::fromArray($response['data'] ?? []);
    }

    /**
     * Partially update a segment (PATCH /segments/{id}).
     *
     * @param  array<string, mixed>  $payload  Any of `name`, `conditions`.
     */
    public function update(int $id, array $payload): Segment
    {
        $response = $this->http->patch('segments/'.$id, ['json' => $payload]);

        return Segment::fromArray($response['data'] ?? []);
    }

    /**
     * Delete a segment (DELETE /segments/{id}).
     */
    public function delete(int $id): void
    {
        $this->http->delete('segments/'.$id);
    }
}
