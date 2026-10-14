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

namespace NetFusion\Core\IMDG\Partitioning;

class PartitionMap
{
    protected int $partitionCount;
    /** @var array<int, Partition> */
    protected array $partitions = [];
    protected ConsistentHashRing $ring;
    protected int $backupCount;

    public function __construct(int $partitionCount = 271, int $backupCount = 1, ?ConsistentHashRing $ring = null)
    {
        $this->partitionCount = max(1, $partitionCount);
        $this->backupCount = max(0, $backupCount);
        $this->ring = $ring ?? new ConsistentHashRing();

        for ($i = 0; $i < $this->partitionCount; $i++) {
            $this->partitions[$i] = new Partition($i);
        }
    }

    public function getPartitionCount(): int
    {
        return $this->partitionCount;
    }

    public function getRing(): ConsistentHashRing
    {
        return $this->ring;
    }

    public function getPartitionId(string $key): int
    {
        // Deterministic integer modulo
        $hash = (int) sprintf('%u', crc32($key));
        return $hash % $this->partitionCount;
    }

    public function getPartition(int $partitionId): ?Partition
    {
        return $this->partitions[$partitionId] ?? null;
    }

    public function getPartitionForKey(string $key): Partition
    {
        $id = $this->getPartitionId($key);
        return $this->partitions[$id];
    }

    public function rebalance(array $members): void
    {
        $this->ring->clear();

        foreach ($members as $node) {
            if ($node->isAlive()) {
                $this->ring->addNode($node);
            }
        }

        if (empty($members)) {
            return;
        }

        for ($i = 0; $i < $this->partitionCount; $i++) {
            $partitionKey = "partition-{$i}";
            $primaryNode = $this->ring->getNode($partitionKey);
            $primaryId = $primaryNode ? $primaryNode->getId() : null;

            $backupNodes = $this->ring->getBackupNodes($partitionKey, $this->backupCount);
            $backupIds = array_map(fn (MemberNode $n) => $n->getId(), $backupNodes);

            $this->partitions[$i]->setPrimaryOwnerId($primaryId);
            $this->partitions[$i]->setBackupOwnerIds($backupIds);
        }
    }

    /**
     * @return array<int, Partition>
     */
    public function getAllPartitions(): array
    {
        return $this->partitions;
    }

    public function clear(): void
    {
        foreach ($this->partitions as $p) {
            $p->clear();
        }
    }
}
