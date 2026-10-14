<?php
/**
 * NetFusion Framework - Enterprise Zero-Knowledge Rollup (ZK-Rollup) Fabric
 *
 * Sparse Merkle Tree (SMT) with deterministic empty-hash levels and cryptographic inclusion proofs.
 *
 * @package    NetFusion\Core\Rollup\State
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Rollup\State;

class SparseMerkleTree
{
    /**
     * Precomputed empty hashes for each level 0..depth.
     *
     * @var array<int, string>
     */
    protected array $emptyHashes = [];

    /**
     * Sparse node storage: key format "level:index" => hash.
     *
     * @var array<string, string>
     */
    protected array $nodes = [];

    /**
     * Map of raw account keys to their leaf indices.
     *
     * @var array<string, int>
     */
    protected array $keyIndexMap = [];

    /**
     * @param int $depth Tree depth (default 16, supporting 65,536 leaves)
     */
    public function __construct(
        protected readonly int $depth = 16
    ) {
        $this->computeEmptyHashes();
    }

    /**
     * Precalculate empty subtree hashes up to root depth.
     */
    protected function computeEmptyHashes(): void
    {
        $this->emptyHashes[0] = hash('sha256', 'NETFUSION_SMT_EMPTY_LEAF');
        for ($i = 1; $i <= $this->depth; $i++) {
            $prev = $this->emptyHashes[$i - 1];
            $this->emptyHashes[$i] = hash('sha256', $prev . $prev);
        }
    }

    /**
     * Map arbitrary key string or int to a deterministic tree leaf index.
     */
    public function keyToIndex(string|int $key): int
    {
        $strKey = (string) $key;
        if (isset($this->keyIndexMap[$strKey])) {
            return $this->keyIndexMap[$strKey];
        }

        if (is_numeric($key) && (int) $key >= 0 && (int) $key < (1 << $depthMax = min($this->depth, 30))) {
            $index = (int) $key;
        } else {
            // Hexadecimal deterministic hash mapped to depth space
            $hex = substr(hash('sha256', $strKey), 0, 8);
            $mask = (1 << min($this->depth, 30)) - 1;
            $index = hexdec($hex) & $mask;
        }

        $this->keyIndexMap[$strKey] = $index;
        return $index;
    }

    /**
     * Retrieve node hash at given level and index.
     */
    public function getNode(int $level, int $index): string
    {
        $key = "{$level}:{$index}";
        return $this->nodes[$key] ?? ($this->emptyHashes[$level] ?? '');
    }

    /**
     * Update or insert leaf value and recalculate root hash.
     */
    public function update(string|int $key, string $valueHash): string
    {
        $index = $this->keyToIndex($key);
        $currKey = "0:{$index}";

        if ($valueHash === $this->emptyHashes[0]) {
            unset($this->nodes[$currKey]);
        } else {
            $this->nodes[$currKey] = $valueHash;
        }

        $currIndex = $index;
        for ($level = 1; $level <= $this->depth; $level++) {
            $parentIndex = intdiv($currIndex, 2);
            $isRight = ($currIndex % 2 === 1);

            $leftIndex  = $parentIndex * 2;
            $rightIndex = $leftIndex + 1;

            $leftHash  = $this->getNode($level - 1, $leftIndex);
            $rightHash = $this->getNode($level - 1, $rightIndex);

            $parentHash = hash('sha256', $leftHash . $rightHash);
            $pKey = "{$level}:{$parentIndex}";

            if ($parentHash === $this->emptyHashes[$level]) {
                unset($this->nodes[$pKey]);
            } else {
                $this->nodes[$pKey] = $parentHash;
            }

            $currIndex = $parentIndex;
        }

        return $this->getRoot();
    }

    /**
     * Retrieve current state root hash of the Sparse Merkle Tree.
     */
    public function getRoot(): string
    {
        return $this->getNode($this->depth, 0);
    }

    /**
     * Generate cryptographic Merkle inclusion audit path for a key.
     *
     * @return array<int, array{level: int, sibling_hash: string, is_right: bool}>
     */
    public function getProof(string|int $key): array
    {
        $index = $this->keyToIndex($key);
        $proof = [];
        $currIndex = $index;

        for ($level = 0; $level < $this->depth; $level++) {
            $isRight = ($currIndex % 2 === 1);
            $siblingIndex = $isRight ? ($currIndex - 1) : ($currIndex + 1);

            $proof[] = [
                'level'        => $level,
                'sibling_hash' => $this->getNode($level, $siblingIndex),
                'is_right'     => $isRight,
            ];

            $currIndex = intdiv($currIndex, 2);
        }

        return $proof;
    }

    /**
     * Cryptographically verify an inclusion audit proof against a target root.
     *
     * @param array<int, array{level: int, sibling_hash: string, is_right: bool}> $proof
     */
    public function verifyProof(string|int $key, string $valueHash, array $proof, string $expectedRoot): bool
    {
        $currentHash = $valueHash;

        foreach ($proof as $step) {
            $siblingHash = $step['sibling_hash'];
            $isRight = $step['is_right'];

            if ($isRight) {
                // Current is right child, sibling is left
                $currentHash = hash('sha256', $siblingHash . $currentHash);
            } else {
                // Current is left child, sibling is right
                $currentHash = hash('sha256', $currentHash . $siblingHash);
            }
        }

        return hash_equals($expectedRoot, $currentHash);
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getNodeCount(): int
    {
        return count($this->nodes);
    }
}
