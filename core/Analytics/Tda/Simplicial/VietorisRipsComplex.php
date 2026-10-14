<?php
/**
 * NetFusion Framework - Enterprise Topological Data Analysis (TDA) Engine
 *
 * Vietoris-Rips filtered simplicial complex builder from point clouds.
 *
 * @package    NetFusion\Core\Analytics\Tda\Simplicial
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Analytics\Tda\Simplicial;

use NetFusion\Core\Analytics\Tda\Enums\DistanceMetric;
use NetFusion\Core\Analytics\Tda\Exceptions\TdaComputationException;

class VietorisRipsComplex
{
    /**
     * @var float[][] Pairwise distance matrix [i][j]
     */
    private array $distMatrix = [];

    /**
     * @var Simplex[] All simplices sorted in filtration order
     */
    private array $simplices = [];

    /**
     * @param array<int, float[]> $pointCloud Array of coordinate vectors
     * @param float $maxFiltration Max distance radius epsilon
     * @param int $maxDimension Max simplex dimension (e.g. 1 for graphs, 2 for triangles)
     */
    public function __construct(
        private readonly array $pointCloud,
        private readonly float $maxFiltration = 2.0,
        private readonly int $maxDimension = 2,
        private readonly DistanceMetric $metric = DistanceMetric::Euclidean
    ) {
        $numPoints = count($this->pointCloud);
        if ($numPoints === 0) {
            throw new TdaComputationException('Point cloud cannot be empty.');
        }

        $this->computeDistances();
        $this->buildComplex();
    }

    public function getNumPoints(): int
    {
        return count($this->pointCloud);
    }

    public function getMaxFiltration(): float
    {
        return $this->maxFiltration;
    }

    public function getMaxDimension(): int
    {
        return $this->maxDimension;
    }

    /**
     * @return Simplex[]
     */
    public function getSimplices(): array
    {
        return $this->simplices;
    }

    public function getSimplexCount(): int
    {
        return count($this->simplices);
    }

    public function getDistance(int $i, int $j): float
    {
        return $this->distMatrix[$i][$j];
    }

    private function computeDistances(): void
    {
        $n = count($this->pointCloud);
        $this->distMatrix = [];

        for ($i = 0; $i < $n; $i++) {
            $row = [];
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    $row[] = 0.0;
                } elseif ($j < $i) {
                    $row[] = $this->distMatrix[$j][$i];
                } else {
                    $row[] = $this->metric->calculate($this->pointCloud[$i], $this->pointCloud[$j]);
                }
            }
            $this->distMatrix[] = $row;
        }
    }

    private function buildComplex(): void
    {
        $n = count($this->pointCloud);
        $all = [];

        // 0-simplices (vertices) birth at epsilon = 0.0
        for ($i = 0; $i < $n; $i++) {
            $all[] = new Simplex([$i], 0.0);
        }

        // 1-simplices (edges) birth at pairwise distance d(i, j)
        if ($this->maxDimension >= 1) {
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $d = $this->distMatrix[$i][$j];
                    if ($d <= $this->maxFiltration) {
                        $all[] = new Simplex([$i, $j], $d);
                    }
                }
            }
        }

        // 2-simplices (triangles) birth at max pairwise distance: max(d(i,j), d(j,k), d(i,k))
        if ($this->maxDimension >= 2) {
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $d12 = $this->distMatrix[$i][$j];
                    if ($d12 > $this->maxFiltration) {
                        continue;
                    }
                    for ($k = $j + 1; $k < $n; $k++) {
                        $d23 = $this->distMatrix[$j][$k];
                        $d13 = $this->distMatrix[$i][$k];
                        $diam = max($d12, $d23, $d13);
                        if ($diam <= $this->maxFiltration) {
                            $all[] = new Simplex([$i, $j, $k], $diam);
                        }
                    }
                }
            }
        }

        // Sort simplices in valid filtration order:
        // 1. By filtration radius ascending
        // 2. By dimension ascending (faces before co-faces)
        usort($all, function (Simplex $a, Simplex $b) {
            $rA = $a->getFiltrationRadius();
            $rB = $b->getFiltrationRadius();
            if (abs($rA - $rB) > 1e-9) {
                return ($rA < $rB) ? -1 : 1;
            }
            return $a->getDimension() <=> $b->getDimension();
        });

        $this->simplices = $all;
    }
}
