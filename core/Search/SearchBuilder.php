<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Search/SearchBuilder.php
| Module    : Search
| Namespace : NetFusion\Core\Search
| Purpose   : Fluent query builder for searching models and hydrating results.
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

namespace NetFusion\Core\Search;

use NetFusion\Core\Database\Model;

/**
 * Class SearchBuilder
 *
 * Fluent search builder that coordinates between SearchManager and ActiveRecord Models,
 * applying filters, limits, and hydrating database records in ranked relevance order.
 *
 * @package NetFusion\Core\Search
 */
class SearchBuilder
{
    /**
     * Filter constraints.
     *
     * @var array<string, mixed>
     */
    protected array $filters = [];

    /**
     * Max items to retrieve.
     */
    protected ?int $limit = null;

    /**
     * Result offset.
     */
    protected int $offset = 0;

    /**
     * Current page number (1-based).
     */
    protected int $page = 1;

    /**
     * Items per page.
     */
    protected int $perPage = 15;

    /**
     * Custom search driver to use.
     */
    protected ?string $driver = null;

    /**
     * Cached search result.
     */
    protected ?SearchResult $result = null;

    /**
     * @param class-string<Model> $modelClass Target model class name
     * @param string              $query      Search query string
     * @param SearchManager|null  $search     Search manager instance
     */
    public function __construct(
        protected string $modelClass,
        protected string $query,
        protected ?SearchManager $search = null
    ) {
        $this->search = $search ?? (function_exists('app') && app()->has('search') ? app('search') : new SearchManager());
    }

    /**
     * Add a filter constraint.
     *
     * @param string $field
     * @param mixed  $value
     * @return $this
     */
    public function where(string $field, mixed $value): static
    {
        $this->filters[$field] = $value;
        $this->result = null;
        return $this;
    }

    /**
     * Set result limit.
     *
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;
        $this->perPage = $limit;
        $this->result = null;
        return $this;
    }

    /**
     * Set result offset.
     *
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset): static
    {
        $this->offset = $offset;
        $this->result = null;
        return $this;
    }

    /**
     * Specify page and items per page.
     *
     * @param int $page
     * @param int $perPage
     * @return $this
     */
    public function page(int $page, int $perPage = 15): static
    {
        $this->page = max(1, $page);
        $this->perPage = $perPage;
        $this->limit = $perPage;
        $this->offset = ($this->page - 1) * $perPage;
        $this->result = null;
        return $this;
    }

    /**
     * Use a specific search backend driver.
     *
     * @param string $driver
     * @return $this
     */
    public function using(string $driver): static
    {
        $this->driver = $driver;
        $this->result = null;
        return $this;
    }

    /**
     * Execute search and return raw SearchResult value object.
     *
     * @return SearchResult
     */
    public function raw(): SearchResult
    {
        if ($this->result !== null) {
            return $this->result;
        }

        /** @var Model $instance */
        $instance = new $this->modelClass();
        $indexName = method_exists($instance, 'searchableAs')
            ? $instance->searchableAs()
            : (method_exists($instance, 'getTable') ? $instance->getTable() : 'default');

        $options = [
            'filters' => $this->filters,
            'limit'   => $this->limit ?? $this->perPage,
            'offset'  => $this->offset,
            'page'    => $this->page,
        ];

        $driver = $this->search->driver($this->driver);
        $raw = $driver->search($indexName, $this->query, $options);

        return $this->result = new SearchResult(
            hits: $raw['hits'] ?? [],
            total: $raw['total'] ?? 0,
            took: $raw['took'] ?? 0.0,
            query: $this->query,
            page: $this->page,
            perPage: $this->limit ?? $this->perPage
        );
    }

    /**
     * Execute search, retrieve corresponding models from database,
     * and return them sorted by search rank order.
     *
     * @return array<Model>
     */
    public function get(): array
    {
        $searchResult = $this->raw();
        $ids = $searchResult->ids();

        if (empty($ids)) {
            return [];
        }

        /** @var Model $instance */
        $instance = new $this->modelClass();
        $keyName = method_exists($instance, 'getKeyName') ? $instance->getKeyName() : 'id';

        // Fetch records matching IDs
        /** @var array<Model> $records */
        if (method_exists($this->modelClass, 'query')) {
            $builder = forward_static_call([$this->modelClass, 'query']);
            $rows = $builder->whereIn($keyName, $ids)->get();
            $records = array_map(fn($row) => forward_static_call([$this->modelClass, 'newFromBuilder'], $row), $rows);
        } else {
            $records = [];
        }

        // Map models by their ID for fast lookup
        $keyedModels = [];
        foreach ($records as $model) {
            $id = method_exists($model, 'getKey') ? (string) $model->getKey() : (string) ($model->$keyName ?? '');
            $keyedModels[$id] = $model;
        }

        // Sort models preserving the exact ranking order of the search hits
        $sortedModels = [];
        foreach ($ids as $id) {
            $strId = (string) $id;
            if (isset($keyedModels[$strId])) {
                $sortedModels[] = $keyedModels[$strId];
            }
        }

        return $sortedModels;
    }

    /**
     * Get the first matching model or null.
     *
     * @return Model|null
     */
    public function first(): ?Model
    {
        $models = $this->limit(1)->get();
        return $models[0] ?? null;
    }

    /**
     * Get total count of matching records.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->raw()->total();
    }

    /**
     * Get array of matching document IDs.
     *
     * @return array<string|int>
     */
    public function ids(): array
    {
        return $this->raw()->ids();
    }

    /**
     * Paginate search results.
     *
     * @param int $perPage
     * @param int $page
     * @return SearchResult
     */
    public function paginate(int $perPage = 15, int $page = 1): SearchResult
    {
        return $this->page($page, $perPage)->raw();
    }
}
