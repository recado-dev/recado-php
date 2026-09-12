<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\Tag;
use Recado\Sdk\Http\HttpClient;

/**
 * The Tags resource: list plus full CRUD.
 *
 * `create()` is create-or-**find**: a name that already exists (compared
 * case-insensitively, so `VIP` matches `vip`) comes back UNCHANGED — the first
 * casing wins and a create call never repaints a tag someone already curated.
 * Use `update()` to edit one.
 */
final readonly class TagsResource
{
    public function __construct(private HttpClient $http) {}

    /**
     * List all tags (GET /tags). This endpoint is a flat array, not paginated.
     *
     * @return array<int, Tag>
     */
    public function list(): array
    {
        $response = $this->http->get('tags');

        $tags = [];

        foreach ($response['data'] ?? [] as $tag) {
            if (is_array($tag)) {
                $tags[] = Tag::fromArray($tag);
            }
        }

        return $tags;
    }

    /**
     * Create or find a tag by name (POST /tags).
     *
     * The preference-center trio makes the tag an opt-in checkbox subscribers
     * can tick: with `is_public` on it is shown labelled by `public_label`
     * (falling back to the name).
     *
     * @param  array<string, mixed>  $attributes  Optional `color` (`#rrggbb`),
     *                                            `is_public`, `public_label`,
     *                                            `public_description`. Ignored
     *                                            when an existing tag matched.
     */
    public function create(string $name, array $attributes = []): Tag
    {
        $response = $this->http->post('tags', [
            'json' => array_merge($attributes, ['name' => $name]),
        ]);

        return Tag::fromArray($response['data'] ?? []);
    }

    /**
     * Partially update a tag (PATCH /tags/{id}).
     *
     * Attachments are kept. `name` stays unique per project (compared
     * case-insensitively); a tag outside the project is `404` + `tag_not_found`.
     *
     * @param  array<string, mixed>  $payload  Any of `name`, `color`, `is_public`,
     *                                         `public_label`, `public_description`.
     */
    public function update(int $id, array $payload): Tag
    {
        $response = $this->http->patch('tags/'.$id, ['json' => $payload]);

        return Tag::fromArray($response['data'] ?? []);
    }

    /**
     * Delete a tag (DELETE /tags/{id}).
     *
     * The tag is detached from every contact carrying it. The contacts survive,
     * but which of them were tagged is not recorded anywhere, segments
     * conditioning on the tag stop matching, and any preference-center checkbox
     * it rendered disappears.
     */
    public function delete(int $id): void
    {
        $this->http->delete('tags/'.$id);
    }
}
