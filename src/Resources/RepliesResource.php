<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\InboundReply;
use Recado\Sdk\Dto\Paginated;
use Recado\Sdk\Http\HttpClient;
use Recado\Sdk\Resources\Concerns\PaginatesResults;

/**
 * Every reply the project received (GET /replies), newest first — the
 * recovery path when a `message.replied` webhook was missed. Needs the
 * `management` scope.
 *
 * Text only: the HTML body and attachment binaries never leave the platform.
 * Unlike the webhook this lists EVERY stored reply; pass `human: true` and
 * `authenticated: true` for exactly the ones the webhook delivers.
 */
final readonly class RepliesResource
{
    use PaginatesResults;

    public function __construct(private HttpClient $http) {}

    /**
     * @param  array<string, mixed>  $query  since / until (ISO 8601, on received_at),
     *                                       email (sender or contact), message_uuid,
     *                                       conversation_uuid, authenticated, human,
     *                                       unread, archived (bools), per_page, page.
     * @return Paginated<InboundReply>
     */
    public function list(array $query = []): Paginated
    {
        $query = array_map(fn (mixed $value): mixed => is_bool($value) ? ($value ? 'true' : 'false') : $value, $query);

        $response = $this->http->get('replies', ['query' => $query]);

        return Paginated::fromArray($response, InboundReply::fromArray(...));
    }

    /**
     * Lazily iterate every matching reply across all pages.
     *
     * @param  array<string, mixed>  $query  the `list()` filters (page is managed automatically).
     * @return \Generator<int, InboundReply>
     */
    public function cursor(array $query = []): \Generator
    {
        return $this->paginate(
            fn (int $page): Paginated => $this->list(array_merge($query, ['page' => $page])),
        );
    }
}
