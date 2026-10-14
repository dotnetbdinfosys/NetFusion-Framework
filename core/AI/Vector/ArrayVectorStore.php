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

namespace NetFusion\Core\AI\Vector;

use NetFusion\Core\AI\Contracts\VectorStoreInterface;

class ArrayVectorStore implements VectorStoreInterface
{
    /**
     * @var array<string, array<string, array{id: string, vector: array<float>, content: string, metadata: array}>>
     */
    protected array $collections = [];

    public function add(string $id, array $vector, string $content = '', array $metadata = [], string $collection = 'default'): void
    {
        if (!isset($this->collections[$collection])) {
            $this->collections[$collection] = [];
        }

        $this->collections[$collection][$id] = [
            'id' => $id,
            'vector' => array_map('floatval', $vector),
            'content' => $content,
            'metadata' => $metadata,
        ];
    }

    public function search(array $queryVector, int $limit = 5, float $minScore = 0.0, string $collection = 'default'): array
    {
        if (!isset($this->collections[$collection]) || empty($this->collections[$collection])) {
            return [];
        }

        $queryVector = array_map('floatval', $queryVector);
        $results = [];

        foreach ($this->collections[$collection] as $item) {
            $score = VectorMath::cosineSimilarity($queryVector, $item['vector']);

            if ($score >= $minScore) {
                $results[] = [
                    'id' => $item['id'],
                    'score' => round($score, 6),
                    'content' => $item['content'],
                    'metadata' => $item['metadata'],
                ];
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    public function delete(string $id, string $collection = 'default'): void
    {
        unset($this->collections[$collection][$id]);
    }

    public function count(string $collection = 'default'): int
    {
        return isset($this->collections[$collection]) ? count($this->collections[$collection]) : 0;
    }

    public function clear(string $collection = 'default'): void
    {
        $this->collections[$collection] = [];
    }

    /**
     * Get all collections.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCollections(): array
    {
        return $this->collections;
    }
}
