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

namespace NetFusion\Core\WAF\Contracts;

use NetFusion\Core\WAF\IP\ThreatContext;

interface WAFInterface
{
    /**
     * Inspect an incoming request and return evaluation result / threat context.
     *
     * @param string $ip Client IP address
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $uri Request URI and path
     * @param array<string, mixed> $headers Request headers
     * @param array<string, mixed> $query Query parameters
     * @param array<string, mixed>|string $body Request body
     * @param array<string, mixed> $cookies Cookie parameters
     * @return ThreatContext
     */
    public function inspect(
        string $ip,
        string $method,
        string $uri,
        array $headers = [],
        array $query = [],
        array|string $body = [],
        array $cookies = []
    ): ThreatContext;

    /**
     * Check if a client IP is currently blocked or quarantined.
     */
    public function isBlocked(string $ip): bool;

    /**
     * Manually block an IP address with optional reason and TTL (seconds).
     */
    public function blockIp(string $ip, string $reason = 'Manual block', int $ttl = 86400): void;

    /**
     * Unblock an IP address.
     */
    public function unblockIp(string $ip): bool;
}
