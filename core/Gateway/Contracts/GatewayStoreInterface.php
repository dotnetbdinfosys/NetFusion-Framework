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

namespace NetFusion\Core\Gateway\Contracts;

use NetFusion\Core\Gateway\Models\Route;
use NetFusion\Core\Gateway\Models\Upstream;

interface GatewayStoreInterface
{
    /**
     * Persists a route definition.
     */
    public function saveRoute(Route $route): void;

    /**
     * Loads all routes from the store.
     *
     * @return Route[]
     */
    public function loadRoutes(): array;

    /**
     * Deletes a route by its ID.
     */
    public function deleteRoute(string $routeId): void;

    /**
     * Persists upstream health state.
     */
    public function saveUpstreamHealth(string $upstreamId, bool $healthy, int $failures): void;

    /**
     * Loads upstream health state.
     *
     * @return array{healthy: bool, failures: int}|null
     */
    public function loadUpstreamHealth(string $upstreamId): ?array;

    /**
     * Stores a rate-limit bucket entry.
     */
    public function setRateBucket(string $key, int $tokens, int $resetAt): void;

    /**
     * Loads a rate-limit bucket entry.
     *
     * @return array{tokens: int, reset_at: int}|null
     */
    public function getRateBucket(string $key): ?array;

    /**
     * Records a gateway request log entry.
     */
    public function logRequest(array $entry): void;
}
