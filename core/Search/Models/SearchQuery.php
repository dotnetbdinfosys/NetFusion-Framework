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

class SearchQuery
{
    /**
     * @param string $queryString Free-text lexical query
     * @param array<float>|null $vector Dense semantic vector embedding for neural search
     * @param array<string, mixed> $filters Exact match or range filters (e.g. ['category' => 'electronics', 'price' => ['min' => 10, 'max' => 500]])
     * @param array<string> $facets Requested facet field names (e.g. ['category', 'brand', 'price_range'])
     * @param array<string, float> $boostFields Field-level BM25 boost weights (e.g. ['title' => 3.0, 'description' => 1.0])
     * @param int $limit Maximum results to return
     * @param int $offset Offset for pagination
     * @param string|null $sortField Sort field or null for relevance score
     * @param string $sortOrder 'asc' or 'desc'
     * @param bool $fuzzy Enable fuzzy typo matching
     * @param float $hybridWeight Lexical vs Vector blend (1.0 = pure lexical BM25, 0.0 = pure vector, 0.5 = balanced hybrid)
     * @param bool $highlight Enable match highlight snippet generation
     */
    public function __construct(
        protected string $queryString = '',
        protected ?array $vector = null,
        protected array $filters = [],
        protected array $facets = [],
        protected array $boostFields = [],
        protected int $limit = 20,
        protected int $offset = 0,
        protected ?string $sortField = null,
        protected string $sortOrder = 'desc',
        protected bool $fuzzy = false,
        protected float $hybridWeight = 0.5,
        protected bool $highlight = true
    ) {
    }

    public static function create(string $query = ''): static
    {
        return new static($query);
    }

    public function getQueryString(): string
    {
        return $this->queryString;
    }

    public function setQueryString(string $query): static
    {
        $this->queryString = $query;
        return $this;
    }

    public function getVector(): ?array
    {
        return $this->vector;
    }

    public function withVector(?array $vector): static
    {
        $this->vector = $vector;
        return $this;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function filter(string $field, mixed $value): static
    {
        $this->filters[$field] = $value;
        return $this;
    }

    public function getFacets(): array
    {
        return $this->facets;
    }

    public function addFacet(string $facetField): static
    {
        if (!in_array($facetField, $this->facets, true)) {
            $this->facets[] = $facetField;
        }
        return $this;
    }

    public function getBoostFields(): array
    {
        return $this->boostFields;
    }

    public function boost(string $field, float $multiplier = 2.0): static
    {
        $this->boostFields[$field] = $multiplier;
        return $this;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function limit(int $limit): static
    {
        $this->limit = max(1, $limit);
        return $this;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function offset(int $offset): static
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    public function getSortField(): ?string
    {
        return $this->sortField;
    }

    public function sortBy(string $field, string $order = 'desc'): static
    {
        $this->sortField = $field;
        $this->sortOrder = strtolower($order) === 'asc' ? 'asc' : 'desc';
        return $this;
    }

    public function getSortOrder(): string
    {
        return $this->sortOrder;
    }

    public function isFuzzy(): bool
    {
        return $this->fuzzy;
    }

    public function withFuzzy(bool $fuzzy = true): static
    {
        $this->fuzzy = $fuzzy;
        return $this;
    }

    public function getHybridWeight(): float
    {
        return $this->hybridWeight;
    }

    public function withHybridWeight(float $weight): static
    {
        $this->hybridWeight = max(0.0, min(1.0, $weight));
        return $this;
    }

    public function shouldHighlight(): bool
    {
        return $this->highlight;
    }

    public function withHighlight(bool $highlight = true): static
    {
        $this->highlight = $highlight;
        return $this;
    }
}
