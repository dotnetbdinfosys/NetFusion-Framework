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

namespace NetFusion\Core\Vector\Store;

use NetFusion\Core\Vector\Contracts\VectorStoreInterface;
use NetFusion\Core\Vector\VectorCollection;
use NetFusion\Core\Vector\VectorDocument;

class ArrayVectorStore implements VectorStoreInterface
{
    /**
     * @var array<string, VectorCollection>
     */
    protected array $collections = [];

    public function saveCollection(VectorCollection $collection): void
    {
        $this->collections[$collection->getName()] = $collection;
    }

    public function getCollection(string $name): ?VectorCollection
    {
        return $this->collections[$name] ?? null;
    }

    public function hasCollection(string $name): bool
    {
        return isset($this->collections[$name]);
    }

    public function deleteCollection(string $name): bool
    {
        if (isset($this->collections[$name])) {
            unset($this->collections[$name]);
            return true;
        }

        return false;
    }

    public function listCollections(): array
    {
        return array_keys($this->collections);
    }

    public function saveDocument(string $collectionName, VectorDocument $document): void
    {
        $collection = $this->getCollection($collectionName);
        if ($collection !== null) {
            $collection->addDocument($document);
        }
    }

    public function deleteDocument(string $collectionName, string $id): bool
    {
        $collection = $this->getCollection($collectionName);
        if ($collection !== null) {
            return $collection->deleteDocument($id);
        }

        return false;
    }

    public function getDocument(string $collectionName, string $id): ?VectorDocument
    {
        $collection = $this->getCollection($collectionName);
        if ($collection !== null) {
            return $collection->getDocument($id);
        }

        return null;
    }

    public function getDocuments(string $collectionName, int $limit = 1000, int $offset = 0): array
    {
        $collection = $this->getCollection($collectionName);
        if ($collection !== null) {
            $docs = $collection->getDocuments();
            return array_slice($docs, $offset, $limit);
        }

        return [];
    }

    public function countDocuments(string $collectionName): int
    {
        $collection = $this->getCollection($collectionName);
        return $collection ? $collection->count() : 0;
    }
}
