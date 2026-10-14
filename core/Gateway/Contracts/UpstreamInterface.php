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

interface UpstreamInterface
{
    /**
     * Returns the upstream host address.
     */
    public function getHost(): string;

    /**
     * Returns the upstream port.
     */
    public function getPort(): int;

    /**
     * Returns the upstream scheme (http/https).
     */
    public function getScheme(): string;

    /**
     * Returns the upstream weight for load balancing.
     */
    public function getWeight(): int;

    /**
     * Returns whether this upstream is healthy.
     */
    public function isHealthy(): bool;

    /**
     * Marks the upstream as healthy.
     */
    public function markHealthy(): void;

    /**
     * Marks the upstream as unhealthy.
     */
    public function markUnhealthy(): void;

    /**
     * Returns the full base URL of this upstream.
     */
    public function getBaseUrl(): string;
}
