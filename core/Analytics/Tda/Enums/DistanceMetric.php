<?php
/**
 * NetFusion Framework - Enterprise Topological Data Analysis (TDA) Engine
 *
 * Distance metrics for high-dimensional point clouds.
 *
 * @package    NetFusion\Core\Analytics\Tda\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Analytics\Tda\Enums;

use NetFusion\Core\Analytics\Tda\Exceptions\TdaComputationException;

enum DistanceMetric: string
{
    case Euclidean = 'euclidean';
    case Manhattan = 'manhattan';
    case Cosine    = 'cosine';

    /**
     * Compute metric distance between two coordinate vectors.
     *
     * @param float[] $p1
     * @param float[] $p2
     */
    public function calculate(array $p1, array $p2): float
    {
        $n = count($p1);
        if ($n !== count($p2) || $n === 0) {
            throw new TdaComputationException('Point dimensions must match and be non-empty.');
        }

        return match ($this) {
            self::Euclidean => $this->calcEuclidean($p1, $p2),
            self::Manhattan => $this->calcManhattan($p1, $p2),
            self::Cosine    => $this->calcCosine($p1, $p2),
        };
    }

    private function calcEuclidean(array $p1, array $p2): float
    {
        $sum = 0.0;
        $n = count($p1);
        for ($i = 0; $i < $n; $i++) {
            $diff = $p1[$i] - $p2[$i];
            $sum += $diff * $diff;
        }
        return sqrt($sum);
    }

    private function calcManhattan(array $p1, array $p2): float
    {
        $sum = 0.0;
        $n = count($p1);
        for ($i = 0; $i < $n; $i++) {
            $sum += abs($p1[$i] - $p2[$i]);
        }
        return $sum;
    }

    private function calcCosine(array $p1, array $p2): float
    {
        $dot = 0.0;
        $n1 = 0.0;
        $n2 = 0.0;
        $n = count($p1);
        for ($i = 0; $i < $n; $i++) {
            $dot += $p1[$i] * $p2[$i];
            $n1 += $p1[$i] * $p1[$i];
            $n2 += $p2[$i] * $p2[$i];
        }
        $denom = sqrt($n1) * sqrt($n2);
        if ($denom < 1e-15) {
            return 1.0;
        }
        $cos = max(-1.0, min(1.0, $dot / $denom));
        return 1.0 - $cos;
    }
}
