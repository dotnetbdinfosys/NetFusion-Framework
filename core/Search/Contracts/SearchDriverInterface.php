<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Search/Contracts/SearchDriverInterface.php
| Module    : Search
| Namespace : NetFusion\Core\Search\Contracts
| Purpose   : Contract for search storage and indexing drivers.
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

namespace NetFusion\Core\Search\Contracts;

/**
 * Interface SearchDriverInterface
 *
 * Defines the contract for all full-text search backend engines in NetFusion.
 *
 * @package NetFusion\Core\Search\Contracts
 */
interface SearchDriverInterface
{
    /**
     * Search an index with query and options.
     *
     * @param string               $index   Index name
     * @param string               $query   Search query string
     * @param array<string, mixed> $options Filter, limit, offset, and driver-specific options
     * @return array{
     *     hits: array<int, array{id: string|int, score: float, document: array<string, mixed>}>,
     *     total: int,
     *     took: float
     * }
     */
    public function search(string $index, string $query, array $options = []): array;

    /**
     * Index one or more documents into the index.
     *
     * Each document must contain an 'id' or primary key field.
     *
     * @param string                            $index     Index name
     * @param array<int, array<string, mixed>>  $documents List of documents to index
     * @return void
     */
    public function index(string $index, array $documents): void;

    /**
     * Delete one or more documents from the index by IDs.
     *
     * @param string                  $index Index name
     * @param array<int|string>|int|string $ids   Document ID or array of IDs
     * @return void
     */
    public function delete(string $index, array|int|string $ids): void;

    /**
     * Flush all documents from the specified index.
     *
     * @param string $index Index name
     * @return void
     */
    public function flush(string $index): void;

    /**
     * Create an index if it does not already exist.
     *
     * @param string        $index  Index name
     * @param array<string> $fields Indexed searchable fields
     * @return void
     */
    public function createIndex(string $index, array $fields = []): void;

    /**
     * Drop an index and its underlying storage.
     *
     * @param string $index Index name
     * @return void
     */
    public function dropIndex(string $index): void;

    /**
     * Determine whether an index exists.
     *
     * @param string $index Index name
     * @return bool
     */
    public function hasIndex(string $index): bool;

    /**
     * Get the total count of documents in the index.
     *
     * @param string $index Index name
     * @return int
     */
    public function count(string $index): int;
}
