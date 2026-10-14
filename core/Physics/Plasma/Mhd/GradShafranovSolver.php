<?php
/**
 * NetFusion Framework - Enterprise Plasma Physics & Fusion Reactor Fabric
 *
 * 2D Grad-Shafranov axisymmetric magnetohydrodynamic (MHD) equilibrium solver.
 *
 * Solves Delta* psi = -mu_0 * R^2 * p'(psi) - F * F'(psi) via finite-difference SOR.
 *
 * @package    NetFusion\Core\Physics\Plasma\Mhd
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Plasma\Mhd;

use NetFusion\Core\Physics\Plasma\Tokamak\ToroidalField;

class GradShafranovSolver
{
    public const MU_0 = 1.25663706212e-6;

    /**
     * Solve 2D Grad-Shafranov equilibrium poloidal flux psi(R, Z) on a grid.
     *
     * @param ToroidalField $tokamak Tokamak geometry parameters
     * @param int $nR Grid points in major radius R
     * @param int $nZ Grid points in vertical axis Z
     * @param float $plasmaPressureCore Core plasma pressure p_0 in Pascals (e.g. 1e5 Pa)
     * @param int $maxIter Maximum SOR relaxation iterations
     * @param float $omega Relaxation parameter (1.0 < omega < 2.0)
     * @return array{
     *     psi: array<int, array<int, float>>,
     *     r_grid: array<int, float>,
     *     z_grid: array<int, float>,
     *     beta_toroidal: float,
     *     beta_poloidal: float,
     *     troyon_limit: float,
     *     converged: bool
     * }
     */
    public function solveEquilibrium(
        ToroidalField $tokamak,
        int $nR = 25,
        int $nZ = 25,
        float $plasmaPressureCore = 1.5e5,
        int $maxIter = 200,
        float $omega = 1.3
    ): array {
        $r0 = $tokamak->getMajorRadius();
        $a  = $tokamak->getMinorRadius();
        $b0 = $tokamak->getToroidalB0();
        $kappa = $tokamak->getElongation();

        // Coordinate spans: R in [R0 - 1.2*a, R0 + 1.2*a], Z in [-1.2*kappa*a, 1.2*kappa*a]
        $rMin = max(0.5, $r0 - (1.2 * $a));
        $rMax = $r0 + (1.2 * $a);
        $zMin = -(1.2 * $kappa * $a);
        $zMax = 1.2 * $kappa * $a;

        $dr = ($rMax - $rMin) / ($nR - 1);
        $dz = ($zMax - $zMin) / ($nZ - 1);

        $rGrid = [];
        for ($i = 0; $i < $nR; $i++) {
            $rGrid[$i] = $rMin + ($i * $dr);
        }

        $zGrid = [];
        for ($j = 0; $j < $nZ; $j++) {
            $zGrid[$j] = $zMin + ($j * $dz);
        }

        // Initialize flux psi array
        $psi = [];
        for ($i = 0; $i < $nR; $i++) {
            $psi[$i] = array_fill(0, $nZ, 0.0);
        }

        // Solov'ev analytical ansatz initial guess for plasma core
        for ($i = 0; $i < $nR; $i++) {
            for ($j = 0; $j < $nZ; $j++) {
                $r = $rGrid[$i];
                $z = $zGrid[$j];
                $ellipDist = pow(($r - $r0) / $a, 2) + pow($z / ($a * $kappa), 2);
                if ($ellipDist <= 1.0) {
                    $psi[$i][$j] = (1.0 - $ellipDist) * 1.0;
                }
            }
        }

        // SOR relaxation iterations
        $converged = false;
        for ($iter = 0; $iter < $maxIter; $iter++) {
            $maxDelta = 0.0;

            for ($i = 1; $i < $nR - 1; $i++) {
                $r = $rGrid[$i];
                for ($j = 1; $j < $nZ - 1; $j++) {
                    $ellipDist = pow(($r - $r0) / $a, 2) + pow($zGrid[$j] / ($a * $kappa), 2);
                    $source = 0.0;
                    if ($ellipDist <= 1.0) {
                        // Source term J_phi = -mu0 * R * dp/dpsi
                        $source = self::MU_0 * ($r * $r) * ($plasmaPressureCore / 1.0);
                    }

                    // Delta* psi finite difference equation:
                    // (psi_i+1 - 2*psi_i + psi_i-1)/dr^2 - (1/R)*(psi_i+1 - psi_i-1)/(2*dr) + (psi_j+1 - 2*psi_j + psi_j-1)/dz^2 = -source
                    $cR1 = 1.0 / ($dr * $dr);
                    $cR2 = 1.0 / (2.0 * $r * $dr);
                    $cZ  = 1.0 / ($dz * $dz);

                    $denom = (2.0 * $cR1) + (2.0 * $cZ);

                    $psiNew = (
                        (($cR1 - $cR2) * $psi[$i + 1][$j]) +
                        (($cR1 + $cR2) * $psi[$i - 1][$j]) +
                        ($cZ * ($psi[$i][$j + 1] + $psi[$i][$j - 1])) +
                        $source
                    ) / $denom;

                    $delta = $psiNew - $psi[$i][$j];
                    $psi[$i][$j] += $omega * $delta;

                    if (abs($delta) > $maxDelta) {
                        $maxDelta = abs($delta);
                    }
                }
            }

            if ($maxDelta < 1e-4) {
                $converged = true;
                break;
            }
        }

        // Average pressure <p> ~ p_0 / 2
        $avgPressure = $plasmaPressureCore * 0.5;

        // Toroidal beta: beta_t = 2 * mu_0 * <p> / B_0^2
        $betaT = (2.0 * self::MU_0 * $avgPressure) / pow($b0, 2);

        // Poloidal beta: beta_p = 2 * mu_0 * <p> / B_theta^2
        $bTheta = $tokamak->getEdgePoloidalField();
        $betaP = ($bTheta > 0.0) ? ((2.0 * self::MU_0 * $avgPressure) / pow($bTheta, 2)) : 0.0;

        // Troyon limit beta_max = beta_N * (I_p_MA / (a * B0)) %
        $betaN = 2.8; // Standard Troyon factor
        $ipMA = $tokamak->getPlasmaCurrent() / 1e6;
        $troyonLimit = ($betaN * ($ipMA / ($a * $b0))) / 100.0;

        return [
            'psi'           => $psi,
            'r_grid'        => $rGrid,
            'z_grid'        => $zGrid,
            'beta_toroidal' => $betaT,
            'beta_poloidal' => $betaP,
            'troyon_limit'  => $troyonLimit,
            'converged'     => $converged,
        ];
    }
}
