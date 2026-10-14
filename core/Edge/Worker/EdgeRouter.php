<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Edge\Worker;

use NetFusion\Core\Edge\Http\EdgeRequest;

/**
 * Edge Routing & Traffic Splitting Engine.
 * Matches URL path patterns, hostnames, HTTP methods, canary deployment weights, and geo-fencing.
 */
class EdgeRouter
{
    /**
     * @var array<int, array{
     *     pattern: string,
     *     worker: string,
     *     methods: array<string>,
     *     weight: int,
     *     country: ?string
     * }>
     */
    protected array $routes = [];

    /**
     * Register an edge route.
     *
     * @param string $pattern Glob or regex URL pattern e.g. 'api.example.com/*' or '/v1/*'
     * @param array<string> $methods Allowed HTTP methods (empty = ALL)
     * @param int $weight Traffic weight for canary testing (1-100)
     * @param string|null $country Geo-fencing country code filter e.g. 'BD', 'US'
     */
    public function addRoute(
        string $pattern,
        string $worker,
        array $methods = [],
        int $weight = 100,
        ?string $country = null
    ): self {
        $this->routes[] = [
            'pattern' => $pattern,
            'worker' => $worker,
            'methods' => array_map('strtoupper', $methods),
            'weight' => max(1, min(100, $weight)),
            'country' => $country !== null ? strtoupper($country) : null,
        ];
        return $this;
    }

    /**
     * Find matching worker name for incoming request.
     */
    public function match(EdgeRequest $request): ?string
    {
        $url = $request->getUrl();
        $path = $request->getPath();
        $method = $request->getMethod();
        $clientCountry = strtoupper($request->getCountry());

        $candidates = [];

        foreach ($this->routes as $route) {
            // 1. Method check
            if (!empty($route['methods']) && !in_array($method, $route['methods'], true)) {
                continue;
            }

            // 2. Country geo-fencing check
            if ($route['country'] !== null && $route['country'] !== $clientCountry) {
                continue;
            }

            // 3. Pattern match (URL or path)
            if ($this->matchPattern($route['pattern'], $url) || $this->matchPattern($route['pattern'], $path)) {
                $candidates[] = $route;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // If single match with 100% weight
        if (count($candidates) === 1 && $candidates[0]['weight'] === 100) {
            return $candidates[0]['worker'];
        }

        // Weighted canary selection
        $random = mt_rand(1, 100);
        $cumulative = 0;
        foreach ($candidates as $cand) {
            $cumulative += $cand['weight'];
            if ($random <= $cumulative) {
                return $cand['worker'];
            }
        }

        return $candidates[0]['worker'];
    }

    /**
     * Check if pattern matches target string (supports wildcard '*').
     */
    protected function matchPattern(string $pattern, string $target): bool
    {
        if ($pattern === '*' || $pattern === $target) {
            return true;
        }

        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
        return (bool)preg_match($regex, $target);
    }

    /**
     * @return array<int, array{pattern: string, worker: string, methods: array<string>, weight: int, country: ?string}>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function clear(): void
    {
        $this->routes = [];
    }
}
