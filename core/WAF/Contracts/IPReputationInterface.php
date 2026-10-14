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

interface IPReputationInterface
{
    /**
     * Check if IP is in whitelist (skips WAF inspection).
     */
    public function isWhitelisted(string $ip): bool;

    /**
     * Check if IP is in blacklist or active auto-ban.
     */
    public function isBlacklisted(string $ip): bool;

    /**
     * Add IP or CIDR to whitelist.
     */
    public function addWhitelist(string $ipOrCidr, string $description = ''): void;

    /**
     * Add IP or CIDR to blacklist.
     */
    public function addBlacklist(string $ipOrCidr, string $reason = '', int $ttl = 0): void;

    /**
     * Remove IP or CIDR from whitelist or blacklist.
     */
    public function remove(string $ipOrCidr): bool;

    /**
     * Match IP against CIDR subnet (e.g. 192.168.1.0/24 or 10.0.0.0/8).
     */
    public function matchesCidr(string $ip, string $cidr): bool;
}
