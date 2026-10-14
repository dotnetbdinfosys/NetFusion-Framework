<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/AI/Contracts/VectorStoreInterface.php
| Module    : AI
| Namespace : NetFusion\Core\AI\Contracts
| Purpose   : Interface for high-dimensional vector embeddings storage and similarity search.
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

namespace NetFusion\Core\AI\Contracts;

interface VectorStoreInterface
{
    /**
     * Add a document vector to the store.
     *
     * @param string               $id
     * @param array<float>         $vector
     * @param string               $content
     * @param array<string, mixed> $metadata
     * @param string               $collection
     */
    public function add(string $id, array $vector, string $content = '', array $metadata = [], string $collection = 'default'): void;

    /**
     * Query vector store by cosine similarity.
     *
     * @param array<float> $queryVector
     * @param int          $limit
     * @param float        $minScore
     * @param string       $collection
     * @return array<array{id: string, score: float, content: string, metadata: array}>
     */
    public function search(array $queryVector, int $limit = 5, float $minScore = 0.0, string $collection = 'default'): array;

    /**
     * Delete a vector from store.
     */
    public function delete(string $id, string $collection = 'default'): void;

    /**
     * Count total stored vectors.
     */
    public function count(string $collection = 'default'): int;

    /**
     * Clear all vectors in a collection.
     */
    public function clear(string $collection = 'default'): void;
}
