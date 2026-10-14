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

interface RateLimiterInterface
{
    /**
     * Checks whether the given request is allowed under current rate limit.
     */
    public function isAllowed(GatewayRequest $request): bool;

    /**
     * Consumes one token for the given request.
     * Returns true if the token was consumed, false if limit is exceeded.
     */
    public function consume(GatewayRequest $request): bool;

    /**
     * Returns the remaining allowed requests for this key within the window.
     */
    public function getRemainingTokens(string $key): int;

    /**
     * Returns the Unix timestamp when the current window resets.
     */
    public function getResetTime(string $key): int;
}
