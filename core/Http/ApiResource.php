<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/ApiResource.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Base class for transforming a single model into a standardized API JSON structure.
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
 * NetFusion API Resource
 *
 * Base resource transformer class. Extend this to define the API
 * representation of a model. Override toArray() to control field
 * visibility, naming, and hypermedia links.
 *
 * Example:
 *   class PlanResource extends ApiResource {
 *       public function toArray(): array {
 *           return [
 *               'id'    => $this->resource['id'],
 *               'name'  => $this->resource['name'],
 *               'price' => number_format($this->resource['price'], 2),
 *           ];
 *       }
 *   }
 *
 * Usage:
 *   return ApiResponse::success(new PlanResource($plan));
 *
 * @package NetFusion\Core\Http
 * @version 1.0.0
 */
abstract class ApiResource implements JsonSerializable
{
    /**
     * Additional meta data attached to this resource.
     *
     * @var array<string,mixed>
     */
    protected array $meta = [];

    /**
     * Hypermedia links for this resource.
     *
     * @var array<string,string>
     */
    protected array $links = [];

    /**
     * @param mixed $resource The underlying model or array.
     */
    public function __construct(
        protected readonly mixed $resource
    ) {}

    // =========================================================================
    //  Abstract
    // =========================================================================

    /**
     * Transform the resource into an array representation.
     * Override in concrete resource classes.
     *
     * @return array<string,mixed>
     */
    abstract public function toArray(): array;

    // =========================================================================
    //  Fluent Helpers
    // =========================================================================

    /**
     * Add meta data to the resource.
     *
     * @param  array<string,mixed> $meta
     * @return static
     */
    public function withMeta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);
        return $this;
    }

    /**
     * Add hypermedia links to the resource.
     *
     * @param  array<string,string> $links
     * @return static
     */
    public function withLinks(array $links): static
    {
        $this->links = array_merge($this->links, $links);
        return $this;
    }

    // =========================================================================
    //  Serialization
    // =========================================================================

    /**
     * Serialize the resource to an array (including meta/links if present).
     *
     * @return array<string,mixed>
     */
    public function resolve(): array
    {
        $data = $this->toArray();

        if (!empty($this->meta)) {
            $data['_meta'] = $this->meta;
        }

        if (!empty($this->links)) {
            $data['_links'] = $this->links;
        }

        return $data;
    }

    /**
     * JsonSerializable implementation.
     *
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->resolve();
    }

    /**
     * Get the underlying resource value.
     *
     * @return mixed
     */
    public function getResource(): mixed
    {
        return $this->resource;
    }
}
