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

namespace NetFusion\Core\IMDG\Contracts;

interface EvictionPolicyInterface
{
    /**
     * Record an access event for a key.
     */
    public function recordAccess(string $key): void;

    /**
     * Record an insertion or update for a key.
     */
    public function recordPut(string $key): void;

    /**
     * Record removal of a key.
     */
    public function recordRemove(string $key): void;

    /**
     * Select a key candidate to evict.
     *
     * @return string|null
     */
    public function selectVictim(): ?string;

    /**
     * Clear eviction tracking state.
     */
    public function clear(): void;
}
