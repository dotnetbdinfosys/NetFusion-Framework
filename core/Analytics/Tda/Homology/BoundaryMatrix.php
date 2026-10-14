<?php
/**
 * NetFusion Framework - Enterprise Topological Data Analysis (TDA) Engine
 *
 * Boundary matrix column reduction over Z_2 = {0, 1} for persistent homology.
 *
 * @package    NetFusion\Core\Analytics\Tda\Homology
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Analytics\Tda\Homology;

use NetFusion\Core\Analytics\Tda\Enums\HomologyDimension;
use NetFusion\Core\Analytics\Tda\Simplicial\Simplex;
use NetFusion\Core\Analytics\Tda\Simplicial\VietorisRipsComplex;

class BoundaryMatrix
{
    /**
     * @var Simplex[] Ordered list of filtered simplices
     */
    private array $simplices;

    /**
     * @var array<string, int> Lookup map: simplex key => index in filtered list
     */
    private array $simplexIndexMap = [];

    /**
     * @var array<int, int[]> Reduced columns represented as sorted sets of row indices
     */
    private array $columns = [];

    /**
     * @var PersistenceInterval[]
     */
    private array $intervals = [];

    public function __construct(VietorisRipsComplex $complex)
    {
        $this->simplices = $complex->getSimplices();
        $m = count($this->simplices);

        for ($i = 0; $i < $m; $i++) {
            $this->simplexIndexMap[$this->simplices[$i]->getKey()] = $i;
        }

        $this->buildAndReduce();
    }

    /**
     * @return PersistenceInterval[]
     */
    public function getIntervals(): array
    {
        return $this->intervals;
    }

    /**
     * Filter intervals by homology dimension.
     *
     * @return PersistenceInterval[]
     */
    public function getIntervalsByDimension(HomologyDimension $dimension): array
    {
        $out = [];
        foreach ($this->intervals as $interval) {
            if ($interval->getDimension() === $dimension) {
                $out[] = $interval;
            }
        }
        return $out;
    }

    /**
     * Compute Betti number beta_k at filtration parameter epsilon.
     * Betti number = count of features of dimension k alive at epsilon.
     */
    public function getBettiNumber(HomologyDimension $dimension, float $epsilon): int
    {
        $count = 0;
        foreach ($this->intervals as $interval) {
            if ($interval->getDimension() === $dimension && $interval->isAliveAt($epsilon)) {
                $count++;
            }
        }
        return $count;
    }

    private function buildAndReduce(): void
    {
        $m = count($this->simplices);
        $this->columns = [];

        // 1. Initialize boundary columns for each simplex
        for ($j = 0; $j < $m; $j++) {
            $simplex = $this->simplices[$j];
            $faces = $simplex->getBoundaryFaces();
            $colRows = [];

            foreach ($faces as $face) {
                $faceKey = '[' . implode(',', $face['vertices']) . ']';
                if (isset($this->simplexIndexMap[$faceKey])) {
                    $colRows[] = $this->simplexIndexMap[$faceKey];
                }
            }

            sort($colRows, SORT_NUMERIC);
            $this->columns[$j] = $colRows;
        }

        // 2. Perform Z_2 column reduction using low(j) pivot map
        // lowMap[row] = col
        $lowMap = [];

        for ($j = 0; $j < $m; $j++) {
            while (!empty($this->columns[$j])) {
                $low = end($this->columns[$j]); // Lowest row index with non-zero entry
                if (!isset($lowMap[$low])) {
                    // Unique pivot found!
                    $lowMap[$low] = $j;
                    break;
                }

                // Collision: column $j and $lowMap[$low] share the same low pivot
                // Add column $lowMap[$low] to column $j over Z_2 (symmetric difference)
                $otherCol = $this->columns[$lowMap[$low]];
                $this->columns[$j] = $this->addColumnsZ2($this->columns[$j], $otherCol);
            }
        }

        // 3. Extract persistent homology intervals from paired pivots
        // If lowMap[i] = j, then simplex i (dimension k) creates a feature at birth(i),
        // and simplex j (dimension k+1) destroys it at death(j).
        $pairedDeaths = [];

        foreach ($lowMap as $i => $j) {
            $birthSimplex = $this->simplices[$i];
            $deathSimplex = $this->simplices[$j];

            $dimVal = $birthSimplex->getDimension();
            $dim = HomologyDimension::tryFrom($dimVal);

            if ($dim !== null) {
                $birthRadius = $birthSimplex->getFiltrationRadius();
                $deathRadius = $deathSimplex->getFiltrationRadius();

                // Only register if death > birth (positive lifespan)
                if ($deathRadius > $birthRadius) {
                    $this->intervals[] = new PersistenceInterval($dim, $birthRadius, $deathRadius);
                }
            }

            $pairedDeaths[$i] = true;
        }

        // 4. Any simplex that never dies has an infinite interval [birth, inf)
        for ($i = 0; $i < $m; $i++) {
            if (!isset($pairedDeaths[$i]) && empty($this->columns[$i])) {
                $simplex = $this->simplices[$i];
                $dimVal = $simplex->getDimension();
                $dim = HomologyDimension::tryFrom($dimVal);

                if ($dim !== null) {
                    $this->intervals[] = new PersistenceInterval($dim, $simplex->getFiltrationRadius(), null);
                }
            }
        }

        // Sort intervals by dimension ascending, then birth ascending
        usort($this->intervals, function (PersistenceInterval $a, PersistenceInterval $b) {
            if ($a->getDimension() !== $b->getDimension()) {
                return $a->getDimension()->value <=> $b->getDimension()->value;
            }
            return $a->getBirth() <=> $b->getBirth();
        });
    }

    /**
     * Add two sparse column vectors over Z_2 (symmetric difference).
     *
     * @param int[] $colA
     * @param int[] $colB
     * @return int[]
     */
    private function addColumnsZ2(array $colA, array $colB): array
    {
        $counts = [];
        foreach ($colA as $row) {
            $counts[$row] = ($counts[$row] ?? 0) + 1;
        }
        foreach ($colB as $row) {
            $counts[$row] = ($counts[$row] ?? 0) + 1;
        }

        $res = [];
        foreach ($counts as $row => $cnt) {
            // Over Z_2, 1 + 1 = 0
            if ($cnt % 2 === 1) {
                $res[] = $row;
            }
        }

        sort($res, SORT_NUMERIC);
        return $res;
    }
}
