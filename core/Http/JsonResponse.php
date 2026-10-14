<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/JsonResponse.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Specialized JSON response class with automatic encoding and application/json headers.
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

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/JsonResponse.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Specialized JSON response class with automatic encoding and application/json headers.
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

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/JsonResponse.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Specialized JSON response class with automatic encoding and application/json headers.
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
 * NetFusion JSON Response
 *
 * A specialised Response subclass that automatically:
 *  - JSON-encodes the provided data
 *  - Sets Content-Type to application/json; charset=UTF-8
 *  - Provides a fluent setData() for post-construction changes
 *
 * @package NetFusion\Core\Http
 * @version 1.0.0
 */
class JsonResponse extends Response
{
    /**
     * The raw data to encode.
     */
    private mixed $data;

    /**
     * json_encode() option flags.
     */
    private int $options;

    /**
     * @param mixed $data       The data to JSON-encode.
     * @param int   $statusCode HTTP status code.
     * @param int   $options    json_encode() option flags.
     */
    public function __construct(
        mixed $data       = null,
        int   $statusCode = 200,
        int   $options    = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    ) {
        $this->data    = $data;
        $this->options = $options;

        parent::__construct(
            content   : $this->encode($data),
            statusCode: $statusCode,
            headers   : ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    /**
     * Replace the response data and re-encode.
     *
     * @param  mixed  $data New data to encode.
     * @return static
     */
    public function setData(mixed $data): static
    {
        $this->data    = $data;
        $this->content = $this->encode($data);
        return $this;
    }

    /**
     * Get the raw (un-encoded) data.
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Encode the data to a JSON string.
     *
     * @throws \RuntimeException On JSON encoding failure.
     */
    private function encode(mixed $data): string
    {
        $encoded = json_encode($data, $this->options);

        if ($encoded === false) {
            throw new \RuntimeException(
                sprintf('Failed to JSON-encode response data: %s', json_last_error_msg())
            );
        }

        return $encoded;
    }
}
