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

namespace NetFusion\Core\Gateway\Models;

use NetFusion\Core\Gateway\Contracts\RouteInterface;

/**
 * Defines a gateway routing rule that maps incoming requests to an upstream pool.
 *
 * Matching priority (evaluated in order):
 *  1. Hostname match (exact or wildcard)
 *  2. Path prefix match
 *  3. HTTP method match
 *  4. Header predicate match
 */
class Route implements RouteInterface
{
    private int $priority = 100;

    /** @var string[] Allowed HTTP methods (empty = all) */
    private array $methods = [];

    /** @var array<string, string> Header predicates that must be present and match */
    private array $headerPredicates = [];

    /** Strip path prefix from upstream request? */
    private bool $stripPrefix = false;

    /** Add an upstream path prefix */
    private string $upstreamPath = '';

    public function __construct(
        private readonly string $id,
        private readonly string $pathPrefix,
        private readonly UpstreamPool $upstreamPool,
        private readonly string $hostname = '*'
    ) {}

    // -------------------------------------------------------------------------
    // Fluent builder
    // -------------------------------------------------------------------------

    public function methods(string ...$methods): self
    {
        $this->methods = array_map('strtoupper', $methods);
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headerPredicates[strtolower($name)] = $value;
        return $this;
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function stripPrefix(bool $strip = true): self
    {
        $this->stripPrefix = $strip;
        return $this;
    }

    public function upstreamPath(string $path): self
    {
        $this->upstreamPath = $path;
        return $this;
    }

    // -------------------------------------------------------------------------
    // RouteInterface
    // -------------------------------------------------------------------------

    public function getId(): string
    {
        return $this->id;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getUpstreamPool(): UpstreamPool
    {
        return $this->upstreamPool;
    }

    public function matches(GatewayRequest $request): bool
    {
        // Hostname match
        if ($this->hostname !== '*') {
            $requestHost = strtolower(explode(':', $request->getHost())[0]);
            $routeHost   = strtolower($this->hostname);
            if ($requestHost !== $routeHost) {
                return false;
            }
        }

        // Path prefix match
        $path = $request->getPath();
        if (!str_starts_with($path, $this->pathPrefix)) {
            return false;
        }

        // Method match
        if (!empty($this->methods) && !in_array($request->getMethod(), $this->methods, true)) {
            return false;
        }

        // Header predicate match
        foreach ($this->headerPredicates as $headerName => $expectedValue) {
            $actual = $request->getHeader($headerName);
            if ($actual === null || !str_contains($actual, $expectedValue)) {
                return false;
            }
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Additional accessors
    // -------------------------------------------------------------------------

    public function getPathPrefix(): string
    {
        return $this->pathPrefix;
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function shouldStripPrefix(): bool
    {
        return $this->stripPrefix;
    }

    public function getUpstreamPath(): string
    {
        return $this->upstreamPath;
    }

    public function getAllowedMethods(): array
    {
        return $this->methods;
    }

    /**
     * Rewrites the inbound URI path for the upstream request.
     * If stripPrefix is enabled the path prefix is removed; if
     * upstreamPath is set it is prepended.
     */
    public function rewritePath(string $inboundPath): string
    {
        $path = $inboundPath;
        if ($this->stripPrefix && str_starts_with($path, $this->pathPrefix)) {
            $path = substr($path, strlen($this->pathPrefix)) ?: '/';
        }
        if ($this->upstreamPath !== '') {
            $path = rtrim($this->upstreamPath, '/') . '/' . ltrim($path, '/');
        }
        return $path;
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'path_prefix'    => $this->pathPrefix,
            'hostname'       => $this->hostname,
            'methods'        => $this->methods,
            'headers'        => $this->headerPredicates,
            'strip_prefix'   => $this->stripPrefix,
            'upstream_path'  => $this->upstreamPath,
            'priority'       => $this->priority,
            'upstreams'      => array_map(
                fn($u) => $u->toArray(),
                $this->upstreamPool->all()
            ),
        ];
    }
}
