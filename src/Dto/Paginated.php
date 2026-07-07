<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A generic paginated collection envelope, carrying the mapped items plus the
 * raw `meta` and `links` blocks from the API response.
 *
 * @template T
 */
final readonly class Paginated
{
    /**
     * @param array<int, T>        $data
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $links
     */
    public function __construct(
        public array $data,
        public array $meta,
        public array $links,
    ) {
    }

    /**
     * Map a paginated payload's `data[]` through `$mapItem`, keeping meta/links.
     *
     * @template U
     *
     * @param array<string, mixed>      $payload
     * @param callable(array<string, mixed>): U $mapItem
     *
     * @return self<U>
     */
    public static function fromArray(array $payload, callable $mapItem): self
    {
        $items = [];

        foreach ($payload['data'] ?? [] as $item) {
            if (is_array($item)) {
                $items[] = $mapItem($item);
            }
        }

        return new self(
            data: $items,
            meta: is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
            links: is_array($payload['links'] ?? null) ? $payload['links'] : [],
        );
    }
}
