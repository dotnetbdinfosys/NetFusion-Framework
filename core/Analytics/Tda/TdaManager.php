<?php
/**
 * NetFusion Framework - Enterprise Topological Data Analysis (TDA) Engine
 *
 * Core manager facade for Topological Data Analysis and Persistent Homology.
 *
 * @package    NetFusion\Core\Analytics\Tda
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Analytics\Tda;

use NetFusion\Core\Analytics\Tda\Enums\DistanceMetric;
use NetFusion\Core\Analytics\Tda\Enums\HomologyDimension;
use NetFusion\Core\Analytics\Tda\Homology\BoundaryMatrix;
use NetFusion\Core\Analytics\Tda\Homology\PersistenceInterval;
use NetFusion\Core\Analytics\Tda\Simplicial\VietorisRipsComplex;

class TdaManager
{
    /**
     * Build a filtered Vietoris-Rips simplicial complex from a point cloud.
     *
     * @param array<int, float[]> $pointCloud
     */
    public function buildRipsComplex(
        array $pointCloud,
        float $maxFiltration = 2.0,
        int $maxDimension = 2,
        DistanceMetric $metric = DistanceMetric::Euclidean
    ): VietorisRipsComplex {
        return new VietorisRipsComplex($pointCloud, $maxFiltration, $maxDimension, $metric);
    }

    /**
     * Compute persistent homology intervals (barcodes) for a point cloud.
     *
     * @param array<int, float[]> $pointCloud
     * @return array{
     *     num_points: int,
     *     num_simplices: int,
     *     intervals: array<int, array{dimension: int, birth: float, death: float|null, lifespan: float, infinite: bool}>,
     *     betti_numbers: array{h0: int, h1: int, h2: int}
     * }
     */
    public function computePersistentHomology(
        array $pointCloud,
        float $maxFiltration = 2.0,
        int $maxDimension = 2,
        DistanceMetric $metric = DistanceMetric::Euclidean
    ): array {
        $complex = $this->buildRipsComplex($pointCloud, $maxFiltration, $maxDimension, $metric);
        $boundary = new BoundaryMatrix($complex);

        $intervals = $boundary->getIntervals();
        $intervalData = [];
        foreach ($intervals as $int) {
            $intervalData[] = $int->toArray();
        }

        // Betti numbers at maxFiltration
        $betti0 = $boundary->getBettiNumber(HomologyDimension::H0, $maxFiltration);
        $betti1 = $boundary->getBettiNumber(HomologyDimension::H1, $maxFiltration);
        $betti2 = $boundary->getBettiNumber(HomologyDimension::H2, $maxFiltration);

        return [
            'num_points'    => count($pointCloud),
            'num_simplices' => $complex->getSimplexCount(),
            'intervals'     => $intervalData,
            'betti_numbers' => [
                'h0' => $betti0,
                'h1' => $betti1,
                'h2' => $betti2,
            ],
        ];
    }

    /**
     * Evaluate Betti curve beta_k(epsilon) across a range of filtration values.
     *
     * @param array<int, float[]> $pointCloud
     * @param float[] $filtrationSteps
     * @return array<int, array{epsilon: float, betti_0: int, betti_1: int}>
     */
    public function computeBettiCurve(
        array $pointCloud,
        array $filtrationSteps,
        DistanceMetric $metric = DistanceMetric::Euclidean
    ): array {
        $maxEps = max($filtrationSteps);
        $complex = $this->buildRipsComplex($pointCloud, $maxEps, 2, $metric);
        $boundary = new BoundaryMatrix($complex);

        $curve = [];
        foreach ($filtrationSteps as $eps) {
            $curve[] = [
                'epsilon' => round($eps, 4),
                'betti_0' => $boundary->getBettiNumber(HomologyDimension::H0, $eps),
                'betti_1' => $boundary->getBettiNumber(HomologyDimension::H1, $eps),
            ];
        }

        return $curve;
    }
}
