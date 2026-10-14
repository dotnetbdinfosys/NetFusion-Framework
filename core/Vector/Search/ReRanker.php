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

namespace NetFusion\Core\Vector\Search;

class ReRanker
{
    /**
     * Cross-score and re-rank candidate documents against the original query.
     * Evaluates exact phrase matches, token overlap ratio, and initial retrieval score.
     *
     * @param string $query Original query text
     * @param array<array{id: string, score: float, text?: string, metadata: array<string, mixed>}> $candidates
     * @param int $topK
     * @return array<array{id: string, score: float, original_score: float, rerank_boost: float, metadata: array<string, mixed>}>
     */
    public static function rerank(string $query, array $candidates, int $topK = 10): array
    {
        $queryLower = mb_strtolower(trim($query), 'UTF-8');
        $queryTokens = preg_split('/\s+/u', $queryLower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $queryTokenCount = max(1, count($queryTokens));

        $reranked = [];

        foreach ($candidates as $item) {
            $text = (string)($item['text'] ?? $item['metadata']['text'] ?? $item['metadata']['content'] ?? '');
            $textLower = mb_strtolower($text, 'UTF-8');

            $exactPhraseBoost = 0.0;
            if (!empty($queryLower) && str_contains($textLower, $queryLower)) {
                $exactPhraseBoost = 0.35; // Major boost for exact phrase match
            }

            // Calculate token overlap
            $matchedTokens = 0;
            foreach ($queryTokens as $tok) {
                if (str_contains($textLower, $tok)) {
                    $matchedTokens++;
                }
            }
            $overlapRatio = $matchedTokens / $queryTokenCount;
            $overlapBoost = $overlapRatio * 0.25;

            $origScore = (float)($item['score'] ?? 0.0);
            $totalBoost = $exactPhraseBoost + $overlapBoost;
            $newScore = $origScore + $totalBoost;

            $reranked[] = [
                'id' => $item['id'],
                'score' => (float)$newScore,
                'original_score' => $origScore,
                'rerank_boost' => $totalBoost,
                'text' => $text,
                'metadata' => $item['metadata'] ?? [],
            ];
        }

        // Sort descending
        usort($reranked, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($reranked, 0, $topK);
    }
}
