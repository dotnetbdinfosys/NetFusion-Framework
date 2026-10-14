<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Memory;

class SemanticMemory
{
    /** @var array<string, array{id: string, content: string, metadata: array, tokens: array<string, int>, timestamp: float}> */
    private array $items = [];

    public function store(string $content, array $metadata = []): string
    {
        $id = uniqid('mem_', true);
        $tokens = $this->tokenize($content);

        $this->items[$id] = [
            'id' => $id,
            'content' => $content,
            'metadata' => $metadata,
            'tokens' => $tokens,
            'timestamp' => microtime(true),
        ];

        return $id;
    }

    /**
     * Alias for store().
     */
    public function remember(string $content, array $metadata = []): string
    {
        return $this->store($content, $metadata);
    }

    /**
     * Search and rank long-term memories by semantic relevance to query.
     *
     * @return array<int, array{id: string, content: string, metadata: array, score: float}>
     */
    public function recall(string $query, int $limit = 5): array
    {
        $queryTokens = $this->tokenize($query);
        if (empty($queryTokens)) {
            return [];
        }

        $scores = [];
        foreach ($this->items as $id => $item) {
            $score = $this->calculateRelevance($queryTokens, $item['tokens']);
            if ($score > 0.0) {
                $scores[] = [
                    'id' => $item['id'],
                    'content' => $item['content'],
                    'metadata' => $item['metadata'],
                    'score' => round($score, 4),
                ];
            }
        }

        usort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scores, 0, $limit);
    }

    public function delete(string $id): bool
    {
        if (isset($this->items[$id])) {
            unset($this->items[$id]);
            return true;
        }
        return false;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function clear(): void
    {
        $this->items = [];
    }

    /**
     * @return array<string, int> Term frequency map
     */
    private function tokenize(string $text): array
    {
        $cleaned = strtolower(trim(preg_replace('/[^a-zA-Z0-9_\s]/', ' ', $text) ?? ''));
        $words = preg_split('/\s+/', $cleaned, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tf = [];
        foreach ($words as $w) {
            if (strlen($w) >= 2) {
                $tf[$w] = ($tf[$w] ?? 0) + 1;
            }
        }

        return $tf;
    }

    /**
     * Compute Cosine / TF similarity score between query and document.
     */
    private function calculateRelevance(array $queryTf, array $docTf): float
    {
        $dotProduct = 0;
        $qNorm = 0;
        $dNorm = 0;

        foreach ($queryTf as $term => $qCount) {
            $qNorm += $qCount * $qCount;
            if (isset($docTf[$term])) {
                $dotProduct += $qCount * $docTf[$term];
            }
        }

        foreach ($docTf as $count) {
            $dNorm += $count * $count;
        }

        if ($qNorm == 0 || $dNorm == 0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($qNorm) * sqrt($dNorm));
    }
}
