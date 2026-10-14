<?php
/**
 * NetFusion Framework - Enterprise Superconductivity & Quantum Magnetics Fabric
 *
 * Abrikosov triangular vortex lattice, flux line quantization, and vortex pinning dynamics.
 *
 * @package    NetFusion\Core\Physics\Superconductivity\Vortex
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Superconductivity\Vortex;

use NetFusion\Core\Physics\Superconductivity\Enums\SuperconductorMaterial;
use NetFusion\Core\Physics\Superconductivity\GinzburgLandau\GinzburgLandauEngine;

class AbrikosovVortexLattice
{
    protected GinzburgLandauEngine $glEngine;

    public function __construct(?GinzburgLandauEngine $glEngine = null)
    {
        $this->glEngine = $glEngine ?? new GinzburgLandauEngine();
    }

    /**
     * Compute areal vortex line density n_v = B / Phi_0 (vortices per square meter).
     */
    public function getVortexDensity(float $appliedFieldTesla): float
    {
        if ($appliedFieldTesla <= 0.0) {
            return 0.0;
        }
        return $appliedFieldTesla / GinzburgLandauEngine::FLUX_QUANTUM;
    }

    /**
     * Compute equilibrium triangular vortex-vortex lattice spacing a_Delta in meters:
     * a_Delta = sqrt( 2 * Phi_0 / (sqrt(3) * B) ).
     */
    public function getLatticeSpacing(float $appliedFieldTesla): float
    {
        if ($appliedFieldTesla <= 0.0) {
            return INF;
        }
        return sqrt((2.0 * GinzburgLandauEngine::FLUX_QUANTUM) / (sqrt(3.0) * $appliedFieldTesla));
    }

    /**
     * Model spatial 2D triangular vortex core coordinates within an L_x by L_y sample window.
     *
     * @return array<int, array{x: float, y: float}>
     */
    public function generateLatticeNodes(float $appliedFieldTesla, float $sizeMeters = 1e-6): array
    {
        $a = $this->getLatticeSpacing($appliedFieldTesla);
        if (!is_finite($a) || $a <= 0.0) {
            return [];
        }

        $rowSpacing = $a * (sqrt(3.0) / 2.0);
        $nodes = [];

        $nRows = (int) ceil($sizeMeters / $rowSpacing);
        $nCols = (int) ceil($sizeMeters / $a);

        for ($row = 0; $row <= $nRows; $row++) {
            $y = $row * $rowSpacing;
            $xOffset = ($row % 2 === 1) ? ($a * 0.5) : 0.0;

            for ($col = 0; $col <= $nCols; $col++) {
                $x = ($col * $a) + $xOffset;
                if ($x <= $sizeMeters && $y <= $sizeMeters) {
                    $nodes[] = ['x' => $x, 'y' => $y];
                }
            }
        }

        return $nodes;
    }

    /**
     * Evaluate Lorentz flux depinning force and vortex state under applied transport current.
     *
     * F_L = J * B (N / m^3).
     *
     * @param float $appliedFieldTesla B in Tesla
     * @param float $transportCurrentDensity J in A / m^2
     * @param float $pinningForceDensity F_p in N / m^3 (default 1e9 N/m^3 for pinned YBCO)
     * @return array{
     *     lorentz_force_n_m3: float,
     *     pinning_force_n_m3: float,
     *     is_pinned: bool,
     *     vortex_density_m2: float,
     *     lattice_spacing_nm: float
     * }
     */
    public function evaluateVortexPinning(
        float $appliedFieldTesla,
        float $transportCurrentDensity,
        float $pinningForceDensity = 1.0e9
    ): array {
        $fLorentz = abs($transportCurrentDensity * $appliedFieldTesla);
        $isPinned = ($fLorentz <= $pinningForceDensity);
        $spacing = $this->getLatticeSpacing($appliedFieldTesla);

        return [
            'lorentz_force_n_m3' => $fLorentz,
            'pinning_force_n_m3' => $pinningForceDensity,
            'is_pinned'          => $isPinned,
            'vortex_density_m2'  => $this->getVortexDensity($appliedFieldTesla),
            'lattice_spacing_nm' => is_finite($spacing) ? ($spacing * 1e9) : 0.0,
        ];
    }
}
