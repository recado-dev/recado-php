<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\Conversation;
use Recado\Sdk\Dto\Paginated;
use Recado\Sdk\Http\HttpClient;
use Recado\Sdk\Resources\Concerns\PaginatesResults;

/**
 * The Inbox conversations: read the threads contacts started by replying, and
 * triage them (mark read/unread, archive). Needs the `management` scope.
 *
 * The read/archive state is shared by the whole team (and every key); a new
 * human reply always marks a conversation unread again and brings it back
 * from the archive. Reading a conversation through the API does NOT mark it
 * read — call `markRead()` explicitly.
 */
final readonly class ConversationsResource
{
    use PaginatesResults;

    public function __construct(private HttpClient $http) {}

    /**
     * List conversations, most recent activity first (GET /conversations).
     *
     * @param  array<string, mixed>  $query  unread (bool), archived (bool; omitted
     *                                       = both), email (participant or
     *                                       contact), since / until (ISO 8601, on
     *                                       the last activity), per_page, page.
     * @return Paginated<Conversation>
     */
    public function list(array $query = []): Paginated
    {
        $response = $this->http->get('conversations', ['query' => self::query($query)]);

        return Paginated::fromArray($response, Conversation::fromArray(...));
    }

    /**
     * Lazily iterate every conversation across all pages.
     *
     * @param  array<string, mixed>  $query  the `list()` filters (page is managed automatically).
     * @return \Generator<int, Conversation>
     */
    public function cursor(array $query = []): \Generator
    {
        return $this->paginate(
            fn (int $page): Paginated => $this->list(array_merge($query, ['page' => $page])),
        );
    }

    /**
     * Fetch one conversation with its whole thread (GET /conversations/{uuid}).
     * An unknown uuid is a `NotFoundException` with code `conversation_not_found`.
     */
    public function get(string $uuid): Conversation
    {
        $response = $this->http->get('conversations/'.rawurlencode($uuid));

        return Conversation::fromArray($response['data'] ?? []);
    }

    /**
     * Change the read and/or archived state (PATCH /conversations/{uuid}); a
     * null argument leaves that half as it is.
     */
    public function update(string $uuid, ?bool $read = null, ?bool $archived = null): Conversation
    {
        $payload = array_filter(['read' => $read, 'archived' => $archived], fn (?bool $value): bool => $value !== null);

        $response = $this->http->patch('conversations/'.rawurlencode($uuid), ['json' => $payload]);

        return Conversation::fromArray($response['data'] ?? []);
    }

    public function markRead(string $uuid): Conversation
    {
        return $this->update($uuid, read: true);
    }

    public function markUnread(string $uuid): Conversation
    {
        return $this->update($uuid, read: false);
    }

    public function archive(string $uuid): Conversation
    {
        return $this->update($uuid, archived: true);
    }

    public function unarchive(string $uuid): Conversation
    {
        return $this->update($uuid, archived: false);
    }

    /**
     * Booleans travel as "true"/"false" in the query string.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private static function query(array $query): array
    {
        return array_map(fn (mixed $value): mixed => is_bool($value) ? ($value ? 'true' : 'false') : $value, $query);
    }
}
