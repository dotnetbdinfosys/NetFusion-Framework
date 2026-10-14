<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/ApiResponse.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Standardized API response builder — wraps data in a consistent JSON envelope.
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

/**
 * NetFusion API Response Builder
 *
 * Wraps any data into a standardized JSON envelope format:
 *
 * {
 *     "status": "success",
 *     "code": 200,
 *     "data": {...},
 *     "meta": {"total": 50, "page": 1, "per_page": 15, "last_page": 4},
 *     "_links": {"self": "/api/v1/plans", "next": "/api/v1/plans?page=2"}
 * }
 *
 * All API controllers should use ApiResponse instead of raw Response::json()
 * to ensure consistency across the entire platform.
 *
 * @package NetFusion\Core\Http
 * @version 1.0.0
 */
class ApiResponse
{
    // =========================================================================
    //  Static Factory Methods
    // =========================================================================

    /**
     * Build a success response.
     *
     * @param  mixed                  $data    The response data (model, array, ApiResource, ApiResourceCollection).
     * @param  int                    $status  HTTP status code (default: 200).
     * @param  array<string,mixed>    $meta    Additional meta data.
     * @param  array<string,string>   $links   Hypermedia links.
     * @param  array<string,string>   $headers Additional HTTP headers.
     * @return Response
     */
    public static function success(
        mixed $data    = null,
        int   $status  = 200,
        array $meta    = [],
        array $links   = [],
        array $headers = [],
    ): Response {
        return static::make('success', $data, $status, $meta, $links, $headers);
    }

    /**
     * Build an error response.
     *
     * @param  string               $message Error message.
     * @param  int                  $status  HTTP status code (default: 400).
     * @param  array<string,mixed>  $errors  Additional error details (validation errors etc).
     * @param  array<string,string> $headers Additional HTTP headers.
     * @return Response
     */
    public static function error(
        string $message = 'An error occurred.',
        int    $status  = 400,
        array  $errors  = [],
        array  $headers = [],
    ): Response {
        $body = [
            'status'  => 'error',
            'code'    => $status,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $body['errors'] = $errors;
        }

        $response = Response::json($body, $status);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * Build a 201 Created response.
     *
     * @param  mixed              $data    The created resource.
     * @param  string             $message Optional creation message.
     * @param  array<string,string> $links  Hypermedia links (e.g., 'self' => '/api/v1/plans/1').
     * @return Response
     */
    public static function created(
        mixed  $data    = null,
        string $message = 'Resource created successfully.',
        array  $links   = [],
    ): Response {
        return static::make('success', $data, 201, ['message' => $message], $links);
    }

    /**
     * Build a 204 No Content response.
     *
     * @return Response
     */
    public static function noContent(): Response
    {
        return new Response('', 204);
    }

    /**
     * Build a paginated collection response.
     *
     * @param  ApiResourceCollection $collection The resource collection.
     * @param  array<string,string>  $links       Pagination links (prev/next).
     * @param  array<string,string>  $headers     Additional HTTP headers.
     * @return Response
     */
    public static function paginated(
        ApiResourceCollection $collection,
        array $links   = [],
        array $headers = [],
    ): Response {
        $paginationMeta = $collection->getPaginationMeta();
        $collectionLinks = array_merge($collection->getLinks(), $links);

        return static::make('success', $collection, 200, $paginationMeta, $collectionLinks, $headers);
    }

    // =========================================================================
    //  Internal Builder
    // =========================================================================

    /**
     * Build the JSON response envelope.
     *
     * @param  string               $status
     * @param  mixed                $data
     * @param  int                  $httpStatus
     * @param  array<string,mixed>  $meta
     * @param  array<string,string> $links
     * @param  array<string,string> $headers
     * @return Response
     */
    protected static function make(
        string $status,
        mixed  $data,
        int    $httpStatus,
        array  $meta    = [],
        array  $links   = [],
        array  $headers = [],
    ): Response {
        // Resolve ApiResource or ApiResourceCollection
        if ($data instanceof ApiResource) {
            $resolvedData = $data->resolve();
        } elseif ($data instanceof ApiResourceCollection) {
            $resolvedData = $data->resolve();
            // Merge collection-level pagination meta
            $meta = array_merge($data->getPaginationMeta(), $meta);
            $links = array_merge($data->getLinks(), $links);
        } else {
            $resolvedData = $data;
        }

        $body = [
            'status' => $status,
            'code'   => $httpStatus,
        ];

        // Only include 'data' key when there is data
        if ($resolvedData !== null) {
            $body['data'] = $resolvedData;
        }

        if (!empty($meta)) {
            $body['meta'] = $meta;
        }

        if (!empty($links)) {
            $body['_links'] = $links;
        }

        $response = Response::json($body, $httpStatus);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
