<?php
/**
 * NetFusion Framework - Enterprise Numerical Relativity Engine
 *
 * 4D Covariant Spacetime Metric Tensor g_uv with signature (- + + +).
 *
 * @package    NetFusion\Core\Relativity\Tensor
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Relativity\Tensor;

use NetFusion\Core\Relativity\Enums\MetricType;
use NetFusion\Core\Relativity\Exceptions\RelativityComputationException;
use NetFusion\Core\Relativity\Exceptions\SpacetimeSingularityException;

class MetricTensor
{
    public const SPEED_OF_LIGHT = 299792458.0;          // c in m/s
    public const GRAVITATIONAL_CONSTANT = 6.67430e-11;   // G in m^3 / (kg s^2)
    public const SOLAR_MASS_KG = 1.98847e30;             // M_sun in kg

    /**
     * 4x4 metric components [row][col] where 0=t, 1=r (or x), 2=theta (or y), 3=phi (or z).
     *
     * @var array<int, array<int, float>>
     */
    private array $components;

    /**
     * @param array<int, array<int, float>> $components
     */
    public function __construct(
        private readonly MetricType $type,
        array $components
    ) {
        if (count($components) !== 4) {
            throw new RelativityComputationException('Metric tensor must have 4 rows.');
        }

        for ($i = 0; $i < 4; $i++) {
            if (count($components[$i]) !== 4) {
                throw new RelativityComputationException('Each row in 4D metric tensor must have 4 columns.');
            }
        }

        // Verify symmetry g_uv = g_vu within numerical tolerance
        for ($i = 0; $i < 4; $i++) {
            for ($j = 0; $j < 4; $j++) {
                if (abs($components[$i][$j] - $components[$j][$i]) > 1e-7) {
                    throw new RelativityComputationException("Metric tensor must be symmetric: g[$i][$j] != g[$j][$i]");
                }
            }
        }

        $this->components = $components;
    }

    public static function flatMinkowski(): self
    {
        $g = [
            [-1.0,  0.0,  0.0,  0.0],
            [ 0.0,  1.0,  0.0,  0.0],
            [ 0.0,  0.0,  1.0,  0.0],
            [ 0.0,  0.0,  0.0,  1.0],
        ];
        return new self(MetricType::Minkowski, $g);
    }

    /**
     * Schwarzschild static spherically symmetric black hole in Schwarzschild coordinates (t, r, theta, phi).
     * ds^2 = -(1 - rs/r) dt^2 + (1 - rs/r)^(-1) dr^2 + r^2 dtheta^2 + r^2 sin^2(theta) dphi^2
     *
     * @param float $massSolarMasses Mass in solar masses
     * @param float $radialDistanceMeters Distance r from center in meters
     * @param float $thetaRad Polar angle theta (default pi/2 for equatorial plane)
     */
    public static function schwarzschild(
        float $massSolarMasses,
        float $radialDistanceMeters,
        float $thetaRad = M_PI_2
    ): self {
        if ($massSolarMasses <= 0.0) {
            throw new RelativityComputationException('Black hole mass must be strictly positive.');
        }

        $rs = self::schwarzschildRadiusMeters($massSolarMasses);

        if ($radialDistanceMeters <= $rs) {
            throw new SpacetimeSingularityException("Point is located at or inside Schwarzschild event horizon (r <= rs = {$rs} m).");
        }


        $factor = 1.0 - ($rs / $radialDistanceMeters);
        $sinTheta = sin($thetaRad);

        $g = [
            [-$factor,           0.0,                  0.0,                                      0.0],
            [0.0,                (1.0 / $factor),      0.0,                                      0.0],
            [0.0,                0.0,                  ($radialDistanceMeters * $radialDistanceMeters), 0.0],
            [0.0,                0.0,                  0.0,                                      ($radialDistanceMeters * $radialDistanceMeters * $sinTheta * $sinTheta)],
        ];

        return new self(MetricType::Schwarzschild, $g);
    }

    /**
     * Kerr rotating black hole metric in Boyer-Lindquist coordinates (t, r, theta, phi).
     *
     * @param float $massSolarMasses Mass in solar masses
     * @param float $spinParameterDimensionless Dimensionless spin parameter a* in [0, 1)
     * @param float $radialDistanceMeters Distance r in meters
     * @param float $thetaRad Polar angle theta
     */
    public static function kerr(
        float $massSolarMasses,
        float $spinParameterDimensionless,
        float $radialDistanceMeters,
        float $thetaRad = M_PI_2
    ): self {
        if ($spinParameterDimensionless < 0.0 || $spinParameterDimensionless >= 1.0) {
            throw new RelativityComputationException('Dimensionless Kerr spin parameter must be in range [0, 1).');
        }

        $rs = self::schwarzschildRadiusMeters($massSolarMasses);
        $rg = $rs / 2.0; // Gravitational radius rg = G*M / c^2
        $a = $spinParameterDimensionless * $rg; // Spin angular momentum parameter a

        $r = $radialDistanceMeters;
        if ($r <= 0.0) {
            throw new SpacetimeSingularityException('Kerr radial coordinate must be strictly greater than 0.');
        }

        $cosTheta = cos($thetaRad);
        $sinTheta = sin($thetaRad);
        $sin2 = $sinTheta * $sinTheta;

        $rho2 = ($r * $r) + ($a * $a * $cosTheta * $cosTheta);
        $delta = ($r * $r) - (2.0 * $rg * $r) + ($a * $a);

        if ($delta <= 0.0) {
            throw new SpacetimeSingularityException('Point is situated at or inside Kerr event horizon (Delta <= 0).');
        }

        $gtt = -(1.0 - (2.0 * $rg * $r / $rho2));
        $grr = $rho2 / $delta;
        $gthth = $rho2;
        $gphph = (($r * $r + $a * $a) + (2.0 * $rg * $r * $a * $a * $sin2 / $rho2)) * $sin2;
        $gtph = -(2.0 * $rg * $r * $a * $sin2 / $rho2);

        $g = [
            [$gtt,  0.0,   0.0,   $gtph],
            [0.0,   $grr,  0.0,   0.0],
            [0.0,   0.0,   $gthth,0.0],
            [$gtph, 0.0,   0.0,   $gphph],
        ];

        return new self(MetricType::Kerr, $g);
    }

    /**
     * Schwarzschild radius r_s = 2 * G * M / c^2 in meters.
     */
    public static function schwarzschildRadiusMeters(float $massSolarMasses): float
    {
        $massKg = $massSolarMasses * self::SOLAR_MASS_KG;
        return (2.0 * self::GRAVITATIONAL_CONSTANT * $massKg) / (self::SPEED_OF_LIGHT * self::SPEED_OF_LIGHT);
    }

    public function getType(): MetricType
    {
        return $this->type;
    }

    public function get(int $mu, int $nu): float
    {
        if ($mu < 0 || $mu >= 4 || $nu < 0 || $nu >= 4) {
            throw new RelativityComputationException("Metric indices out of bounds: ($mu, $nu)");
        }
        return $this->components[$mu][$nu];
    }

    /**
     * Compute line element ds^2 = g_uv dx^u dx^v.
     *
     * @param array<int, float> $dx 4-displacement vector (dt, dx1, dx2, dx3)
     */
    public function lineElement(array $dx): float
    {
        if (count($dx) !== 4) {
            throw new RelativityComputationException('Displacement vector dx must have 4 components.');
        }

        $ds2 = 0.0;
        for ($i = 0; $i < 4; $i++) {
            for ($j = 0; $j < 4; $j++) {
                $ds2 += $this->components[$i][$j] * $dx[$i] * $dx[$j];
            }
        }
        return $ds2;
    }

    /**
     * Calculate 4x4 determinant det(g).
     */
    public function determinant(): float
    {
        return $this->calcDeterminant4x4($this->components);
    }

    /**
     * Compute contravariant inverse metric g^uv.
     *
     * @return array<int, array<int, float>>
     */
    public function inverse(): array
    {
        $det = $this->determinant();
        if (abs($det) < 1e-18) {
            throw new RelativityComputationException('Singular metric tensor: determinant is 0.');
        }

        // For diagonal or block-diagonal Kerr metrics, standard 4x4 adjugate / det
        $inv = [];
        for ($i = 0; $i < 4; $i++) {
            $row = [];
            for ($j = 0; $j < 4; $j++) {
                $minor = $this->getMinor3x3($this->components, $j, $i);
                $sign = (($i + $j) % 2 === 0) ? 1.0 : -1.0;
                $row[] = ($sign * $minor) / $det;
            }
            $inv[] = $row;
        }

        return $inv;
    }

    /**
     * @return array<int, array<int, float>>
     */
    public function toArray(): array
    {
        return $this->components;
    }

    private function calcDeterminant4x4(array $m): float
    {
        $det = 0.0;
        for ($col = 0; $col < 4; $col++) {
            $minor = $this->getMinor3x3($m, 0, $col);
            $sign = ($col % 2 === 0) ? 1.0 : -1.0;
            $det += $sign * $m[0][$col] * $minor;
        }
        return $det;
    }

    private function getMinor3x3(array $m, int $excludedRow, int $excludedCol): float
    {
        $sub = [];
        for ($r = 0; $r < 4; $r++) {
            if ($r === $excludedRow) {
                continue;
            }
            $row = [];
            for ($c = 0; $c < 4; $c++) {
                if ($c === $excludedCol) {
                    continue;
                }
                $row[] = $m[$r][$c];
            }
            $sub[] = $row;
        }

        // 3x3 determinant expansion
        return $sub[0][0] * ($sub[1][1] * $sub[2][2] - $sub[1][2] * $sub[2][1])
             - $sub[0][1] * ($sub[1][0] * $sub[2][2] - $sub[1][2] * $sub[2][0])
             + $sub[0][2] * ($sub[1][0] * $sub[2][1] - $sub[1][1] * $sub[2][0]);
    }
}
