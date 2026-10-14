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

use NetFusion\Core\Gateway\Models\UpstreamPool;

interface HealthCheckerInterface
{
    /**
     * Runs a health check against all upstreams in a pool.
     * Returns an array of [host => bool] indicating health status.
     *
     * @return array<string, bool>
     */
    public function checkPool(UpstreamPool $pool): array;

    /**
     * Runs a health check against a single upstream.
     */
    public function checkUpstream(UpstreamInterface $upstream): bool;
}
