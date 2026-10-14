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

use NetFusion\Core\Gateway\Models\GatewayRequest;
use NetFusion\Core\Gateway\Models\UpstreamPool;

interface RouteInterface
{
    /**
     * Returns the unique route identifier.
     */
    public function getId(): string;

    /**
     * Returns whether this route matches the given request.
     */
    public function matches(GatewayRequest $request): bool;

    /**
     * Returns the upstream pool for this route.
     */
    public function getUpstreamPool(): UpstreamPool;

    /**
     * Returns route priority (lower = higher priority).
     */
    public function getPriority(): int;
}
