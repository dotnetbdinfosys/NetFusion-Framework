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

namespace NetFusion\Core\Vector\Contracts;

use NetFusion\Core\Vector\VectorCollection;
use NetFusion\Core\Vector\VectorDocument;

interface VectorStoreInterface
{
    /**
     * Save or update a collection metadata and index graph.
     */
    public function saveCollection(VectorCollection $collection): void;

    /**
     * Load collection by name.
     */
    public function getCollection(string $name): ?VectorCollection;

    /**
     * Check if collection exists.
     */
    public function hasCollection(string $name): bool;

    /**
     * Delete collection and all its vectors.
     */
    public function deleteCollection(string $name): bool;

    /**
     * List all collection names.
     *
     * @return array<string>
     */
    public function listCollections(): array;

    /**
     * Save a document to collection.
     */
    public function saveDocument(string $collectionName, VectorDocument $document): void;

    /**
     * Delete document by ID from collection.
     */
    public function deleteDocument(string $collectionName, string $id): bool;

    /**
     * Get document by ID.
     */
    public function getDocument(string $collectionName, string $id): ?VectorDocument;

    /**
     * Get all documents in collection.
     *
     * @return array<VectorDocument>
     */
    public function getDocuments(string $collectionName, int $limit = 1000, int $offset = 0): array;

    /**
     * Count documents in collection.
     */
    public function countDocuments(string $collectionName): int;
}
