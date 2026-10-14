<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Search\Contracts;

use NetFusion\Core\Search\Models\Document;
use NetFusion\Core\Search\Models\SearchQuery;
use NetFusion\Core\Search\Models\SearchResult;
use NetFusion\Core\Search\Models\Suggestion;

interface SearchEngineInterface
{
    public function createIndex(string $indexName, array $options = []): IndexInterface;

    public function getIndex(string $indexName): IndexInterface;

    public function hasIndex(string $indexName): bool;

    public function deleteIndex(string $indexName): bool;

    public function search(string $indexName, SearchQuery $query): SearchResult;

    /**
     * @return array<Suggestion>
     */
    public function suggest(string $indexName, string $prefix, int $limit = 5): array;
}
