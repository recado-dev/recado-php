<?php

declare(strict_types=1);

namespace Mailer\Sdk\Resources;

use Mailer\Sdk\Dto\Message;
use Mailer\Sdk\Dto\Paginated;
use Mailer\Sdk\Http\HttpClient;
use Mailer\Sdk\Resources\Concerns\PaginatesResults;

/**
 * The Messages resource (read-only).
 */
final readonly class MessagesResource
{
    use PaginatesResults;

    public function __construct(private HttpClient $http)
    {
    }

    /**
     * List messages (GET /messages).
     *
     * @param array<string, mixed> $query status, source, campaign_id, search,
     *                                    per_page, page.
     *
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
     * @param array<string, mixed> $query status, source, campaign_id, search,
     *                                    per_page (page is managed automatically).
     *
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
}
