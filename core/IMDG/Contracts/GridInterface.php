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

use NetFusion\Core\IMDG\DistributedMap;
use NetFusion\Core\IMDG\Partitioning\MemberNode;

interface GridInterface
{
    /**
     * Get or create a distributed map by name.
     *
     * @param string $name
     * @return DistributedMapInterface
     */
    public function getMap(string $name): DistributedMapInterface;

    /**
     * Register a cluster member node.
     */
    public function addMember(MemberNode $node): self;

    /**
     * Remove a cluster member node.
     */
    public function removeMember(string $nodeId): self;

    /**
     * Get all cluster member nodes.
     *
     * @return array<string, MemberNode>
     */
    public function getMembers(): array;

    /**
     * Check cluster health and rebalance partitions.
     */
    public function rebalance(): void;

    /**
     * Clear all distributed maps and near-caches across the grid.
     */
    public function clearAll(): void;

    /**
     * Get grid statistics across all maps and nodes.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array;
}
