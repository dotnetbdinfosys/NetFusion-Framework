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

namespace NetFusion\Core\Data\Replication\Contracts;

use NetFusion\Core\Data\Replication\RegionNode;
use NetFusion\Core\Data\Replication\ReplicationEvent;

interface ReplicationDriverInterface
{
    /**
     * Push events to a remote region node.
     *
     * @param RegionNode         $targetNode
     * @param ReplicationEvent[] $events
     * @return array{success: bool, applied_count: int, error?: string}
     */
    public function push(RegionNode $targetNode, array $events): array;

    /**
     * Pull delta events from a remote region node since given timestamp or sequence ID.
     *
     * @param RegionNode $sourceNode
     * @param int|float  $since
     * @return ReplicationEvent[]
     */
    public function pull(RegionNode $sourceNode, int|float $since): array;
}
