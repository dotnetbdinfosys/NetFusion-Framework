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

class ConsistentHashRing
{
    protected int $vnodes;
    /** @var array<int, string> [hash => nodeId] */
    protected array $ring = [];
    /** @var array<string, MemberNode> [nodeId => MemberNode] */
    protected array $nodes = [];
    /** @var array<int> Sorted array of ring hashes */
    protected array $sortedHashes = [];

    public function __construct(int $vnodes = 50)
    {
        $this->vnodes = max(1, $vnodes);
    }

    public function addNode(MemberNode $node): self
    {
        $this->nodes[$node->getId()] = $node;
        $vnodeCount = $this->vnodes * $node->getWeight();

        for ($i = 0; $i < $vnodeCount; $i++) {
            $vnodeKey = "{$node->getId()}-vn-{$i}";
            $hash = $this->hash($vnodeKey);
            $this->ring[$hash] = $node->getId();
        }

        $this->rebuildSortedHashes();
        return $this;
    }

    public function removeNode(string $nodeId): self
    {
        if (!isset($this->nodes[$nodeId])) {
            return $this;
        }

        unset($this->nodes[$nodeId]);

        foreach ($this->ring as $hash => $id) {
            if ($id === $nodeId) {
                unset($this->ring[$hash]);
            }
        }

        $this->rebuildSortedHashes();
        return $this;
    }

    public function getNode(string $key): ?MemberNode
    {
        if (empty($this->ring)) {
            return null;
        }

        $hash = $this->hash($key);
        $nodeId = $this->findClosestNodeId($hash);

        return $nodeId !== null ? ($this->nodes[$nodeId] ?? null) : null;
    }

    /**
     * Get backup nodes for replication (distinct physical nodes).
     *
     * @param string $key
     * @param int $count
     * @return array<MemberNode>
     */
    public function getBackupNodes(string $key, int $count = 1): array
    {
        if (count($this->nodes) <= 1 || $count <= 0) {
            return [];
        }

        $primary = $this->getNode($key);
        if (!$primary) {
            return [];
        }

        $hash = $this->hash($key);
        $backups = [];
        $visitedNodeIds = [$primary->getId() => true];

        $idx = $this->findHashIndex($hash);
        $totalHashes = count($this->sortedHashes);

        for ($i = 1; $i < $totalHashes && count($backups) < $count; $i++) {
            $nextIdx = ($idx + $i) % $totalHashes;
            $h = $this->sortedHashes[$nextIdx];
            $nodeId = $this->ring[$h];

            if (!isset($visitedNodeIds[$nodeId])) {
                $visitedNodeIds[$nodeId] = true;
                if (isset($this->nodes[$nodeId])) {
                    $backups[] = $this->nodes[$nodeId];
                }
            }
        }

        return $backups;
    }

    /**
     * @return array<string, MemberNode>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function clear(): void
    {
        $this->ring = [];
        $this->nodes = [];
        $this->sortedHashes = [];
    }

    protected function findClosestNodeId(int $hash): ?string
    {
        if (empty($this->sortedHashes)) {
            return null;
        }

        $idx = $this->findHashIndex($hash);
        $targetHash = $this->sortedHashes[$idx];

        return $this->ring[$targetHash] ?? null;
    }

    protected function findHashIndex(int $hash): int
    {
        $low = 0;
        $high = count($this->sortedHashes) - 1;

        if ($hash > $this->sortedHashes[$high] || $hash <= $this->sortedHashes[0]) {
            return 0;
        }

        while ($low <= $high) {
            $mid = (int)(($low + $high) / 2);
            if ($this->sortedHashes[$mid] >= $hash) {
                if ($mid === 0 || $this->sortedHashes[$mid - 1] < $hash) {
                    return $mid;
                }
                $high = $mid - 1;
            } else {
                $low = $mid + 1;
            }
        }

        return 0;
    }

    protected function rebuildSortedHashes(): void
    {
        $this->sortedHashes = array_keys($this->ring);
        sort($this->sortedHashes, SORT_NUMERIC);
    }

    protected function hash(string $key): int
    {
        // 32-bit positive integer hash via CRC32b / MD5
        return (int) sprintf('%u', crc32($key));
    }
}
