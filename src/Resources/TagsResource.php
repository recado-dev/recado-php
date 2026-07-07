<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\Tag;
use Recado\Sdk\Http\HttpClient;

/**
 * The Tags resource (read-only).
 */
final readonly class TagsResource
{
    public function __construct(private HttpClient $http)
    {
    }

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
}
