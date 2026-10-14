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

interface WAFStoreInterface
{
    /**
     * Log a detected threat event or block action.
     */
    public function logThreatEvent(ThreatContext $context): void;

    /**
     * Get recent threat events.
     *
     * @return array<array<string, mixed>>
     */
    public function getRecentThreatEvents(int $limit = 100): array;

    /**
     * Save an IP block with expiration timestamp.
     */
    public function saveIpBlock(string $ip, string $reason, int $expiresAt): void;

    /**
     * Remove an IP block.
     */
    public function removeIpBlock(string $ip): bool;

    /**
     * Check if IP is in storage block list.
     */
    public function isIpBlocked(string $ip): bool;

    /**
     * Get all active IP blocks.
     *
     * @return array<string, array{reason: string, expires_at: int}>
     */
    public function getActiveIpBlocks(): array;

    /**
     * Record metric count (e.g. inspected, passed, blocked, challenged).
     */
    public function recordMetric(string $metric, int $count = 1): void;

    /**
     * Get summary metrics.
     *
     * @return array<string, int>
     */
    public function getMetrics(): array;
}
