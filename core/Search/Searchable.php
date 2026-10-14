<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Search/Searchable.php
| Module    : Search
| Namespace : NetFusion\Core\Search
| Purpose   : Model trait providing searchable capabilities and indexing lifecycle hooks.
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

/**
 * Trait Searchable
 *
 * Adds full-text search capabilities to ActiveRecord Models.
 *
 * Usage:
 *   class Article extends Model {
 *       use Searchable;
 *   }
 *
 *   Article::search('php framework')->where('published', true)->get();
 *
 * @package NetFusion\Core\Search
 */
trait Searchable
{
    /**
     * Get the index name for this model.
     *
     * @return string
     */
    public function searchableAs(): string
    {
        return method_exists($this, 'getTable') ? $this->getTable() : strtolower(class_basename(static::class)) . 's';
    }

    /**
     * Get primary key for indexing.
     *
     * @return string|int
     */
    public function searchableId(): string|int
    {
        return method_exists($this, 'getKey') ? $this->getKey() : ($this->id ?? 0);
    }

    /**
     * Convert model to indexable searchable array.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $data = method_exists($this, 'toArray') ? $this->toArray() : (array) $this;
        $data['id'] = $this->searchableId();

        return $data;
    }

    /**
     * Initiate a search query builder for this model.
     *
     * @param string               $query   Search term
     * @param array<string, mixed> $options Initial options
     * @return SearchBuilder
     */
    public static function search(string $query, array $options = []): SearchBuilder
    {
        $search = $options['search'] ?? (function_exists('app') && app()->has('search') ? app('search') : null);
        $builder = new SearchBuilder(static::class, $query, $search);

        if (isset($options['filters'])) {
            foreach ($options['filters'] as $k => $v) {
                $builder->where($k, $v);
            }
        }
        if (isset($options['limit'])) {
            $builder->limit((int) $options['limit']);
        }
        if (isset($options['driver'])) {
            $builder->using((string) $options['driver']);
        }

        return $builder;
    }

    /**
     * Push this individual model into the search index.
     *
     * @param SearchManager|null $search
     * @return void
     */
    public function searchIndex(?SearchManager $search = null): void
    {
        $search = $search ?? (function_exists('app') && app()->has('search') ? app('search') : new SearchManager());
        $search->index($this->searchableAs(), [$this->toSearchableArray()]);
    }

    /**
     * Remove this individual model from the search index.
     *
     * @param SearchManager|null $search
     * @return void
     */
    public function searchRemove(?SearchManager $search = null): void
    {
        $search = $search ?? (function_exists('app') && app()->has('search') ? app('search') : new SearchManager());
        $search->delete($this->searchableAs(), $this->searchableId());
    }

    /**
     * Index all records of this model in the database into the search engine.
     *
     * @param SearchManager|null $search
     * @param int                $chunkSize
     * @return int Total records indexed
     */
    public static function makeAllSearchable(?SearchManager $search = null, int $chunkSize = 500): int
    {
        $search = $search ?? (function_exists('app') && app()->has('search') ? app('search') : new SearchManager());

        $instance = new static();
        $index = $instance->searchableAs();

        $allModels = method_exists(static::class, 'all') ? static::all() : [];
        $total = 0;
        $batch = [];

        foreach ($allModels as $model) {
            $batch[] = $model->toSearchableArray();
            $total++;

            if (count($batch) >= $chunkSize) {
                $search->index($index, $batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            $search->index($index, $batch);
        }

        return $total;
    }

    /**
     * Flush all documents from the model's search index.
     *
     * @param SearchManager|null $search
     * @return void
     */
    public static function removeAllFromSearch(?SearchManager $search = null): void
    {
        $search = $search ?? (function_exists('app') && app()->has('search') ? app('search') : new SearchManager());
        $instance = new static();
        $search->flush($instance->searchableAs());
    }
}
