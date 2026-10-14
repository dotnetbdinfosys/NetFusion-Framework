<?php
/**
 * NetFusion Framework - Enterprise Computational Fluid Dynamics (CFD / LBM) Fabric
 *
 * Discrete velocity vectors, lattice weights, and equilibrium distribution formulation for D2Q9.
 *
 * @package    NetFusion\Core\Physics\Fluid\Lattice
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Fluid\Lattice;

class DiscreteVelocity
{
    /**
     * D2Q9 Discrete velocity vectors (c_x, c_y).
     *
     * @var array<int, array{0: int, 1: int}>
     */
    public const C = [
        0 => [ 0,  0], // Center (Rest particle)
        1 => [ 1,  0], // East
        2 => [ 0,  1], // North
        3 => [-1,  0], // West
        4 => [ 0, -1], // South
        5 => [ 1,  1], // North-East
        6 => [-1,  1], // North-West
        7 => [-1, -1], // South-West
        8 => [ 1, -1], // South-East
    ];

    /**
     * D2Q9 Lattice weights (w_i).
     *
     * @var array<int, float>
     */
    public const W = [
        0 => 4.0 / 9.0,
        1 => 1.0 / 9.0,
        2 => 1.0 / 9.0,
        3 => 1.0 / 9.0,
        4 => 1.0 / 9.0,
        5 => 1.0 / 36.0,
        6 => 1.0 / 36.0,
        7 => 1.0 / 36.0,
        8 => 1.0 / 36.0,
    ];

    /**
     * Opposite directions for no-slip bounce-back boundary reflection.
     *
     * @var array<int, int>
     */
    public const OPPOSITE = [
        0 => 0,
        1 => 3,
        2 => 4,
        3 => 1,
        4 => 2,
        5 => 7,
        6 => 8,
        7 => 5,
        8 => 6,
    ];

    /**
     * Lattice sound speed squared: c_s^2 = 1/3.
     */
    public const CS2 = 1.0 / 3.0;

    /**
     * Calculate Maxwell-Boltzmann equilibrium distribution for a discrete direction i.
     *
     * f_i^(eq) = w_i * rho * [1 + (c_i . u)/cs^2 + (c_i . u)^2 / (2 cs^4) - (u . u)/(2 cs^2)]
     */
    public static function equilibrium(int $i, float $rho, float $ux, float $uy): float
    {
        $cx = self::C[$i][0];
        $cy = self::C[$i][1];
        $w  = self::W[$i];

        $ci_u = $cx * $ux + $cy * $uy;
        $u_sq = $ux * $ux + $uy * $uy;

        // Factored with cs^2 = 1/3
        $term = 1.0 + 3.0 * $ci_u + 4.5 * ($ci_u * $ci_u) - 1.5 * $u_sq;

        return $w * $rho * $term;
    }

    /**
     * Compute equilibrium distribution array for all 9 discrete directions.
     *
     * @return array<int, float>
     */
    public static function allEquilibrium(float $rho, float $ux, float $uy): array
    {
        $feq = [];
        $u_sq = $ux * $ux + $uy * $uy;

        for ($i = 0; $i < 9; $i++) {
            $cx = self::C[$i][0];
            $cy = self::C[$i][1];
            $ci_u = $cx * $ux + $cy * $uy;
            $term = 1.0 + 3.0 * $ci_u + 4.5 * ($ci_u * $ci_u) - 1.5 * $u_sq;
            $feq[$i] = self::W[$i] * $rho * $term;
        }

        return $feq;
    }
}
