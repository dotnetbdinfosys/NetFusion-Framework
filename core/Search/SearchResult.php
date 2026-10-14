<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Search/SearchResult.php
| Module    : Search
| Namespace : NetFusion\Core\Search
| Purpose   : Search result value object containing hits, scores, and pagination.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\Search;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Class SearchResult
 *
 * Represents an immutable, serializable search query result set with hits,
 * scores, document payloads, and execution latency.
 *
 * @package NetFusion\Core\Search
 * @implements IteratorAggregate<int, array{id: string|int, score: float, document: array<string, mixed>}>
 */
class SearchResult implements Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param array<int, array{id: string|int, score: float, document: array<string, mixed>}> $hits
     * @param int    $total   Total matching documents found
     * @param float  $took    Execution time in milliseconds
     * @param string $query   Original query string
     * @param int    $page    Current page number (1-based)
     * @param int    $perPage Items per page
     */
    public function __construct(
        protected array $hits,
        protected int $total,
        protected float $took = 0.0,
        protected string $query = '',
        protected int $page = 1,
        protected int $perPage = 15
    ) {}

    /**
     * Get the search hits list.
     *
     * @return array<int, array{id: string|int, score: float, document: array<string, mixed>}>
     */
    public function hits(): array
    {
        return $this->hits;
    }

    /**
     * Extract only document IDs from hits preserving ranking order.
     *
     * @return array<int, string|int>
     */
    public function ids(): array
    {
        return array_map(fn(array $hit) => $hit['id'], $this->hits);
    }

    /**
     * Extract only raw document payloads from hits.
     *
     * @return array<int, array<string, mixed>>
     */
    public function documents(): array
    {
        return array_map(fn(array $hit) => $hit['document'], $this->hits);
    }

    /**
     * Get total number of matched documents in the index.
     *
     * @return int
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Count of hits returned in current result page.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->hits);
    }

    /**
     * Execution time in milliseconds.
     *
     * @return float
     */
    public function took(): float
    {
        return $this->took;
    }

    /**
     * Original search query.
     *
     * @return string
     */
    public function query(): string
    {
        return $this->query;
    }

    /**
     * Current page number.
     *
     * @return int
     */
    public function page(): int
    {
        return $this->page;
    }

    /**
     * Number of items per page.
     *
     * @return int
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Calculate the last page number.
     *
     * @return int
     */
    public function lastPage(): int
    {
        return $this->perPage > 0 ? (int) ceil($this->total / $this->perPage) : 1;
    }

    /**
     * Check if there are more pages.
     *
     * @return bool
     */
    public function hasMorePages(): bool
    {
        return $this->page < $this->lastPage();
    }

    /**
     * Determine if result is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->hits);
    }

    /**
     * Determine if result is not empty.
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Iterate over hits.
     *
     * @return Traversable<int, array{id: string|int, score: float, document: array<string, mixed>}>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->hits);
    }

    /**
     * Convert the result to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'query'     => $this->query,
            'total'     => $this->total,
            'took'      => $this->took,
            'page'      => $this->page,
            'per_page'  => $this->perPage,
            'last_page' => $this->lastPage(),
            'hits'      => $this->hits,
        ];
    }

    /**
     * Serialize to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
