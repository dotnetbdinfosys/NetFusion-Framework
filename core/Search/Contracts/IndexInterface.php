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

interface IndexInterface
{
    public function getName(): string;

    public function addDocument(Document $doc): static;

    /**
     * @param array<Document> $docs
     */
    public function addDocuments(array $docs): static;

    public function getDocument(string $id): ?Document;

    public function hasDocument(string $id): bool;

    public function deleteDocument(string $id): bool;

    public function count(): int;

    public function flush(): void;

    public function search(SearchQuery $query): SearchResult;
}
