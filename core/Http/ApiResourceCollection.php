<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/ApiResourceCollection.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Transforms a collection of models into a paginated API resource array.
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

namespace NetFusion\Core\Http;

use JsonSerializable;

/**
 * NetFusion API Resource Collection
 *
 * Wraps a collection of items (models/arrays) and transforms each one
 * using a specified ApiResource class. Supports optional pagination meta.
 *
 * Example:
 *   return ApiResponse::success(
 *       new ApiResourceCollection($plans, PlanResource::class)
 *   );
 *
 * @package NetFusion\Core\Http
 * @version 1.0.0
 */
class ApiResourceCollection implements JsonSerializable
{
    /**
     * Pagination meta data.
     *
     * @var array<string,mixed>
     */
    protected array $paginationMeta = [];

    /**
     * Hypermedia links for the collection.
     *
     * @var array<string,string>
     */
    protected array $links = [];

    /**
     * @param iterable    $items         Collection of raw models or arrays.
     * @param string|null $resourceClass Optional ApiResource subclass FQCN to use for transformation.
     *                                   If null, items are returned as-is.
     */
    public function __construct(
        protected readonly iterable $items,
        protected readonly ?string  $resourceClass = null,
    ) {}

    // =========================================================================
    //  Fluent Helpers
    // =========================================================================

    /**
     * Attach pagination meta data to the collection.
     *
     * @param  int    $total    Total number of items in the dataset.
     * @param  int    $perPage  Items per page.
     * @param  int    $page     Current page number.
     * @param  int    $lastPage Last page number.
     * @return static
     */
    public function withPagination(int $total, int $perPage, int $page, int $lastPage): static
    {
        $this->paginationMeta = [
            'total'     => $total,
            'per_page'  => $perPage,
            'page'      => $page,
            'last_page' => $lastPage,
        ];
        return $this;
    }

    /**
     * Attach hypermedia links to the collection.
     *
     * @param  array<string,string> $links
     * @return static
     */
    public function withLinks(array $links): static
    {
        $this->links = $links;
        return $this;
    }

    // =========================================================================
    //  Transformation
    // =========================================================================

    /**
     * Resolve the collection to an array of transformed items.
     *
     * @return array<int,mixed>
     */
    public function resolve(): array
    {
        $transformed = [];

        foreach ($this->items as $item) {
            if ($this->resourceClass !== null) {
                /** @var ApiResource $resource */
                $resource      = new ($this->resourceClass)($item);
                $transformed[] = $resource->resolve();
            } else {
                $transformed[] = $item;
            }
        }

        return $transformed;
    }

    /**
     * Get pagination meta (if set).
     *
     * @return array<string,mixed>
     */
    public function getPaginationMeta(): array
    {
        return $this->paginationMeta;
    }

    /**
     * Get hypermedia links.
     *
     * @return array<string,string>
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * JsonSerializable implementation.
     *
     * @return array<int,mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->resolve();
    }
}
