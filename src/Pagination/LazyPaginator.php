<?php

declare(strict_types=1);

namespace Recado\Sdk\Pagination;

use Recado\Sdk\Dto\Paginated;

/**
 * Framework-agnostic helper that lazily walks every page of a paginated
 * endpoint and yields each mapped item, one at a time, so callers can iterate
 * an entire collection without manually tracking page numbers.
 *
 * The core SDK never depends on Illuminate; consumers that want a
 * `LazyCollection` can wrap the returned generator themselves
 * (`LazyCollection::make($generator)`).
 */
final class LazyPaginator
{
    /**
     * Yield every item across all pages.
     *
     * Starts at page 1 and keeps advancing while there are more pages. The
     * stop condition is `meta.current_page >= meta.last_page`; as a robust
     * fallback (when `meta` is absent) it also stops when a page returns an
     * empty `data` array or when `links.next` is null.
     *
     * @template T
     *
     * @param callable(int): Paginated<T> $fetchPage receives the 1-based page number.
     *
     * @return \Generator<int, T>
     */
    public static function generate(callable $fetchPage): \Generator
    {
        $page = 1;

        while (true) {
            $result = $fetchPage($page);

            foreach ($result->data as $item) {
                yield $item;
            }

            if (! self::hasMorePages($result, $page)) {
                return;
            }

            $page++;
        }
    }

    /**
     * Decide whether another page should be fetched after the given one.
     *
     * @param Paginated<mixed> $result
     */
    private static function hasMorePages(Paginated $result, int $page): bool
    {
        if ($result->data === []) {
            return false;
        }

        $currentPage = isset($result->meta['current_page']) ? (int) $result->meta['current_page'] : $page;
        $lastPage = isset($result->meta['last_page']) ? (int) $result->meta['last_page'] : null;

        if ($lastPage !== null) {
            return $currentPage < $lastPage;
        }

        // No `last_page` in meta: fall back to the `next` link when present.
        if (array_key_exists('next', $result->links)) {
            return $result->links['next'] !== null;
        }

        // Neither signal available: assume the non-empty page was the last one.
        return false;
    }
}
