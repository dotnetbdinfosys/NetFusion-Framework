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

namespace NetFusion\Core\Search\Models;

class SearchResult
{
    /**
     * @param array<array{document: Document, score: float, highlight?: string, lexical_score?: float, vector_score?: float}> $hits
     * @param int $total Total matching records in index (before limit/offset)
     * @param float $executionTimeMs Query execution latency in milliseconds
     * @param array<string, FacetResult> $facets Aggregated faceted taxonomy counts
     * @param array<Suggestion> $suggestions Spell corrections or auto-suggest matches
     * @param float $maxScore Maximum relevance score among hits
     */
    public function __construct(
        protected array $hits = [],
        protected int $total = 0,
        protected float $executionTimeMs = 0.0,
        protected array $facets = [],
        protected array $suggestions = [],
        protected float $maxScore = 0.0
    ) {
    }

    public static function empty(float $timeMs = 0.0): static
    {
        return new static([], 0, $timeMs);
    }

    /**
     * @return array<array{document: Document, score: float, highlight?: string, lexical_score?: float, vector_score?: float}>
     */
    public function getHits(): array
    {
        return $this->hits;
    }

    /**
     * @return array<Document>
     */
    public function getDocuments(): array
    {
        return array_map(fn($hit) => $hit['document'], $this->hits);
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function count(): int
    {
        return count($this->hits);
    }

    public function getExecutionTimeMs(): float
    {
        return $this->executionTimeMs;
    }

    /**
     * @return array<string, FacetResult>
     */
    public function getFacets(): array
    {
        return $this->facets;
    }

    public function getFacet(string $field): ?FacetResult
    {
        return $this->facets[$field] ?? null;
    }

    /**
     * @return array<Suggestion>
     */
    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    public function getMaxScore(): float
    {
        return $this->maxScore;
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'count' => count($this->hits),
            'execution_time_ms' => $this->executionTimeMs,
            'max_score' => $this->maxScore,
            'hits' => array_map(function ($hit) {
                return [
                    'id' => $hit['document']->getId(),
                    'score' => $hit['score'],
                    'lexical_score' => $hit['lexical_score'] ?? null,
                    'vector_score' => $hit['vector_score'] ?? null,
                    'fields' => $hit['document']->getFields(),
                    'highlight' => $hit['highlight'] ?? null,
                ];
            }, $this->hits),
            'facets' => array_map(fn(FacetResult $f) => [
                'field' => $f->getField(),
                'counts' => $f->getCounts(),
                'ranges' => $f->getRanges(),
            ], $this->facets),
            'suggestions' => array_map(fn(Suggestion $s) => [
                'text' => $s->getText(),
                'score' => $s->getScore(),
            ], $this->suggestions),
        ];
    }
}
