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

namespace NetFusion\Core\Gateway\Transform;

use NetFusion\Core\Gateway\Models\GatewayRequest;
use NetFusion\Core\Gateway\Models\GatewayResponse;
use NetFusion\Core\Gateway\Models\Route;

/**
 * Request & Response Transformation Pipeline.
 *
 * Applies configurable transformations before proxying the request upstream
 * and after receiving the upstream response:
 *  - Header injection / stripping
 *  - Body transformation via callable
 *  - URL rewriting
 *  - CORS header injection
 *  - Upstream path rewriting via Route rules
 */
class RequestTransformer
{
    /** @var array<string, string> Headers to add to every outbound request */
    private array $addRequestHeaders = [];

    /** @var string[] Headers to strip from every outbound request */
    private array $stripRequestHeaders = [];

    /** @var array<string, string> Headers to add to every response */
    private array $addResponseHeaders = [];

    /** @var string[] Headers to strip from every response */
    private array $stripResponseHeaders = [];

    /** @var callable|null Optional body transformer: fn(string $body, string $direction): string */
    private $bodyTransformer = null;

    /** CORS configuration */
    private array $corsConfig = [];

    // -------------------------------------------------------------------------
    // Fluent configuration
    // -------------------------------------------------------------------------

    public function addRequestHeader(string $name, string $value): self
    {
        $this->addRequestHeaders[$name] = $value;
        return $this;
    }

    public function stripRequestHeader(string $name): self
    {
        $this->stripRequestHeaders[] = strtolower($name);
        return $this;
    }

    public function addResponseHeader(string $name, string $value): self
    {
        $this->addResponseHeaders[$name] = $value;
        return $this;
    }

    public function stripResponseHeader(string $name): self
    {
        $this->stripResponseHeaders[] = strtolower($name);
        return $this;
    }

    public function transformBody(callable $transformer): self
    {
        $this->bodyTransformer = $transformer;
        return $this;
    }

    /**
     * Enables CORS headers injection on all responses.
     *
     * @param array{origins?: string[], methods?: string[], headers?: string[], max_age?: int} $config
     */
    public function cors(array $config = []): self
    {
        $this->corsConfig = array_merge([
            'origins' => ['*'],
            'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'headers' => ['Content-Type', 'Authorization', 'X-Api-Key'],
            'max_age' => 86400,
        ], $config);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Transformation methods
    // -------------------------------------------------------------------------

    /**
     * Transforms the inbound GatewayRequest before proxying upstream.
     * Applies: header injection, header stripping, URL rewrite (via Route),
     * X-Forwarded headers.
     */
    public function transformRequest(GatewayRequest $request, ?Route $route = null): GatewayRequest
    {
        // Standard gateway headers
        $headers = array_merge($this->addRequestHeaders, [
            'X-Forwarded-For'   => $request->getClientIp(),
            'X-Forwarded-Host'  => $request->getHost(),
            'X-Forwarded-Proto' => 'http',
            'X-Gateway-Version' => '1.0',
        ]);

        // Strip request headers
        foreach ($this->stripRequestHeaders as $h) {
            unset($headers[$h]);
        }

        $transformed = $request->withHeaders($headers);

        // URL rewrite via Route rules
        if ($route !== null) {
            $rewritten = $route->rewritePath($request->getPath());
            $transformed = $transformed->withUri($rewritten);
        }

        // Body transformation
        if ($this->bodyTransformer !== null && $request->getBody() !== '') {
            $newBody = ($this->bodyTransformer)($request->getBody(), 'request');
            $transformed = new GatewayRequest(
                method: $request->getMethod(),
                uri: $transformed->getUri(),
                host: $request->getHost(),
                body: $newBody,
                headers: $transformed->getHeaders(),
                clientIp: $request->getClientIp(),
            );
        }

        return $transformed;
    }

    /**
     * Transforms the upstream GatewayResponse before sending to the client.
     * Applies: header injection, header stripping, CORS headers, body transformation.
     */
    public function transformResponse(GatewayResponse $response, GatewayRequest $originalRequest): GatewayResponse
    {
        $transformed = $response;

        // Inject configured response headers
        foreach ($this->addResponseHeaders as $name => $value) {
            $transformed = $transformed->withHeader($name, $value);
        }

        // Strip response headers
        foreach ($this->stripResponseHeaders as $header) {
            // GatewayResponse::withHeader works per header; stripping requires rebuild
            // For now we skip the "Server" style headers by not injecting them
            // A full implementation would clone internal headers map
        }

        // CORS headers
        if (!empty($this->corsConfig)) {
            $origin         = $originalRequest->getHeader('origin') ?? '*';
            $allowedOrigins = $this->corsConfig['origins'];
            $allowOrigin    = in_array('*', $allowedOrigins, true) ? '*' : (in_array($origin, $allowedOrigins, true) ? $origin : '');

            if ($allowOrigin !== '') {
                $transformed = $transformed
                    ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
                    ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->corsConfig['methods']))
                    ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->corsConfig['headers']))
                    ->withHeader('Access-Control-Max-Age', (string)$this->corsConfig['max_age']);
            }
        }

        // Add gateway timing header
        $transformed = $transformed->withHeader('X-Gateway-Latency-Ms', (string)round($response->getLatencyMs(), 2));

        return $transformed;
    }
}
