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

namespace NetFusion\Core\IMDG\Vector;

class GridVectorIndex
{
    /** @var array<string, array<float>> */
    protected array $vectors = [];
    /** @var array<string, array<string, mixed>> */
    protected array $metadata = [];
    protected string $metric;

    public function __construct(string $metric = 'cosine')
    {
        $this->metric = strtolower($metric);
    }

    public function index(string $key, array $vector, array $metadata = []): void
    {
        $this->vectors[$key] = array_map('floatval', $vector);
        $this->metadata[$key] = $metadata;
    }

    public function remove(string $key): void
    {
        unset($this->vectors[$key], $this->metadata[$key]);
    }

    /**
     * Search nearest neighbors for query vector.
     *
     * @param array<float> $queryVector
     * @param int $topK
     * @param float $minScore
     * @return array<array{key: string, score: float, metadata: array<string, mixed>}>
     */
    public function search(array $queryVector, int $topK = 5, float $minScore = 0.0): array
    {
        if (empty($this->vectors)) {
            return [];
        }

        $query = array_map('floatval', $queryVector);
        $scores = [];

        foreach ($this->vectors as $key => $vec) {
            $score = match ($this->metric) {
                'euclidean' => $this->euclideanSimilarity($query, $vec),
                'dot' => $this->dotProduct($query, $vec),
                default => $this->cosineSimilarity($query, $vec)
            };

            if ($score >= $minScore) {
                $scores[] = [
                    'key' => $key,
                    'score' => $score,
                    'metadata' => $this->metadata[$key] ?? [],
                ];
            }
        }

        usort($scores, fn ($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scores, 0, $topK);
    }

    public function size(): int
    {
        return count($this->vectors);
    }

    public function clear(): void
    {
        $this->vectors = [];
        $this->metadata = [];
    }

    protected function cosineSimilarity(array $a, array $b): float
    {
        $len = min(count($a), count($b));
        if ($len === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    protected function dotProduct(array $a, array $b): float
    {
        $len = min(count($a), count($b));
        $dot = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
        }
        return $dot;
    }

    protected function euclideanSimilarity(array $a, array $b): float
    {
        $len = min(count($a), count($b));
        $distSq = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $diff = $a[$i] - $b[$i];
            $distSq += $diff * $diff;
        }
        return 1.0 / (1.0 + sqrt($distSq));
    }
}
