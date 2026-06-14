<?php

declare(strict_types=1);

namespace Mailer\Sdk\Resources;

use Mailer\Sdk\Dto\Campaign;
use Mailer\Sdk\Dto\Paginated;
use Mailer\Sdk\Http\HttpClient;
use Mailer\Sdk\Resources\Concerns\PaginatesResults;

/**
 * The Campaigns resource (read-only by design). The management API does not
 * expose sending/scheduling through the SDK, keeping the safe-by-default
 * posture: a campaign send can never be triggered from here.
 */
final readonly class CampaignsResource
{
    use PaginatesResults;

    public function __construct(private HttpClient $http)
    {
    }

    /**
     * List campaigns (GET /campaigns). List items do not embed stats.
     *
     * @param array<string, mixed> $query per_page, page.
     *
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
     * @param array<string, mixed> $query per_page (page is managed automatically).
     *
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
     */
    public function get(int $id): Campaign
    {
        $response = $this->http->get('campaigns/'.$id);

        return Campaign::fromArray($response['data'] ?? []);
    }
}
