<?php
/**
 * NetFusion Framework - Enterprise Zero-Knowledge Rollup (ZK-Rollup) Fabric
 *
 * Merkle Mountain Range (MMR) append-only accumulator with logarithmic peak bagging and proofs.
 *
 * @package    NetFusion\Core\Rollup\State
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Rollup\State;

use NetFusion\Core\Rollup\Exceptions\MmrInclusionProofException;

class MerkleMountainRange
{
    /**
     * Stored leaf hashes (0-indexed).
     *
     * @var array<int, string>
     */
    protected array $leaves = [];

    /**
     * Cache of tree peaks: array of peak hashes.
     *
     * @var array<int, string>|null
     */
    protected ?array $cachedPeaks = null;

    /**
     * Append a leaf hash to the MMR.
     *
     * @return int 0-based leaf index
     */
    public function append(string $leafHash): int
    {
        $index = count($this->leaves);
        $this->leaves[] = $leafHash;
        $this->cachedPeaks = null; // Invalidate peaks cache
        return $index;
    }

    /**
     * Get total number of leaves appended.
     */
    public function getLeafCount(): int
    {
        return count($this->leaves);
    }

    /**
     * Calculate mountain breakdown (leaf counts for each mountain).
     *
     * For example, count 7 = 4 + 2 + 1.
     *
     * @return array<int, array{height: int, count: int, offset: int}>
     */
    public function getMountainStructure(): array
    {
        $n = count($this->leaves);
        $mountains = [];
        $offset = 0;

        // Iterate bit positions from highest to lowest
        for ($h = 30; $h >= 0; $h--) {
            $pow = 1 << $h;
            if (($n & $pow) !== 0) {
                $mountains[] = [
                    'height' => $h,
                    'count'  => $pow,
                    'offset' => $offset,
                ];
                $offset += $pow;
            }
        }

        return $mountains;
    }

    /**
     * Compute the peak hash of a single mountain tree.
     *
     * @param array<int, string> $mountainLeaves
     */
    protected function computeMountainPeak(array $mountainLeaves): string
    {
        $currentLevel = $mountainLeaves;

        while (count($currentLevel) > 1) {
            $nextLevel = [];
            $count = count($currentLevel);
            for ($i = 0; $i < $count; $i += 2) {
                $left  = $currentLevel[$i];
                $right = $currentLevel[$i + 1];
                $nextLevel[] = hash('sha256', $left . $right);
            }
            $currentLevel = $nextLevel;
        }

        return $currentLevel[0] ?? hash('sha256', 'NETFUSION_MMR_EMPTY');
    }

    /**
     * Retrieve array of peak hashes for all current mountains.
     *
     * @return array<int, string>
     */
    public function getPeaks(): array
    {
        if ($this->cachedPeaks !== null) {
            return $this->cachedPeaks;
        }

        $peaks = [];
        $mountains = $this->getMountainStructure();

        foreach ($mountains as $m) {
            $mountainLeaves = array_slice($this->leaves, $m['offset'], $m['count']);
            $peaks[] = $this->computeMountainPeak($mountainLeaves);
        }

        $this->cachedPeaks = $peaks;
        return $peaks;
    }

    /**
     * Compute bagged root hash of the entire MMR.
     */
    public function getRoot(): string
    {
        $peaks = $this->getPeaks();

        if (empty($peaks)) {
            return hash('sha256', 'NETFUSION_MMR_EMPTY');
        }

        if (count($peaks) === 1) {
            return $peaks[0];
        }

        // Canonical right-to-left peak bagging
        $count = count($peaks);
        $bag = $peaks[$count - 1];

        for ($i = $count - 2; $i >= 0; $i--) {
            $bag = hash('sha256', $peaks[$i] . $bag);
        }

        return $bag;
    }

    /**
     * Generate an inclusion proof for a leaf index.
     *
     * @return array{leaf_index: int, mountain_height: int, local_index: int, merkle_path: array, peaks: array}
     */
    public function getProof(int $leafIndex): array
    {
        if ($leafIndex < 0 || $leafIndex >= count($this->leaves)) {
            throw new MmrInclusionProofException("Leaf index {$leafIndex} out of range in MMR.");
        }

        $mountains = $this->getMountainStructure();
        $targetMountain = null;
        $mountainIndex = 0;

        foreach ($mountains as $idx => $m) {
            if ($leafIndex >= $m['offset'] && $leafIndex < ($m['offset'] + $m['count'])) {
                $targetMountain = $m;
                $mountainIndex = $idx;
                break;
            }
        }

        if ($targetMountain === null) {
            throw new MmrInclusionProofException("Could not locate mountain for leaf {$leafIndex}.");
        }

        // Generate Merkle path within this mountain
        $mountainLeaves = array_slice($this->leaves, $targetMountain['offset'], $targetMountain['count']);
        $localIndex = $leafIndex - $targetMountain['offset'];
        $merklePath = [];
        $currentLevel = $mountainLeaves;
        $currIdx = $localIndex;

        while (count($currentLevel) > 1) {
            $isRight = ($currIdx % 2 === 1);
            $siblingIdx = $isRight ? ($currIdx - 1) : ($currIdx + 1);

            $merklePath[] = [
                'sibling_hash' => $currentLevel[$siblingIdx],
                'is_right'     => $isRight,
            ];

            // Compute next level
            $nextLevel = [];
            $count = count($currentLevel);
            for ($i = 0; $i < $count; $i += 2) {
                $nextLevel[] = hash('sha256', $currentLevel[$i] . $currentLevel[$i + 1]);
            }
            $currentLevel = $nextLevel;
            $currIdx = intdiv($currIdx, 2);
        }

        return [
            'leaf_index'      => $leafIndex,
            'mountain_index'  => $mountainIndex,
            'mountain_height' => $targetMountain['height'],
            'local_index'     => $localIndex,
            'merkle_path'     => $merklePath,
            'peaks'           => $this->getPeaks(),
        ];
    }

    /**
     * Cryptographically verify an inclusion proof against an expected bagged MMR root.
     *
     * @param array{leaf_index: int, mountain_index: int, merkle_path: array, peaks: array} $proof
     */
    public static function verifyProof(string $leafHash, array $proof, string $expectedRoot): bool
    {
        $currentHash = $leafHash;
        $merklePath = $proof['merkle_path'] ?? [];

        foreach ($merklePath as $step) {
            $siblingHash = $step['sibling_hash'];
            $isRight = $step['is_right'];

            if ($isRight) {
                $currentHash = hash('sha256', $siblingHash . $currentHash);
            } else {
                $currentHash = hash('sha256', $currentHash . $siblingHash);
            }
        }

        // Reconstruct bagged peaks
        $peaks = $proof['peaks'] ?? [];
        $mountainIdx = $proof['mountain_index'] ?? 0;

        if (!isset($peaks[$mountainIdx])) {
            return false;
        }

        // Peak computed from proof path must match peak stored in proof
        if (!hash_equals($currentHash, $peaks[$mountainIdx])) {
            return false;
        }

        // Bag all peaks
        if (count($peaks) === 1) {
            $bagged = $peaks[0];
        } else {
            $count = count($peaks);
            $bagged = $peaks[$count - 1];
            for ($i = $count - 2; $i >= 0; $i--) {
                $bagged = hash('sha256', $peaks[$i] . $bagged);
            }
        }

        return hash_equals($expectedRoot, $bagged);
    }
}
