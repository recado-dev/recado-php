<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources\Concerns;

use Recado\Sdk\Dto\Paginated;
use Recado\Sdk\Pagination\LazyPaginator;

/**
 * Shared cursor logic for paginated resources: turn a per-page fetch closure
 * into a generator that yields every mapped DTO across all pages.
 */
trait PaginatesResults
{
    /**
     * Yield every item across all pages, fetching one page at a time.
     *
     * @template T
     *
     * @param callable(int): Paginated<T> $fetchPage receives the 1-based page number.
     *
     * @return \Generator<int, T>
     */
    private function paginate(callable $fetchPage): \Generator
    {
        yield from LazyPaginator::generate($fetchPage);
    }
}
