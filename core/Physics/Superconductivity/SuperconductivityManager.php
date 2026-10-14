<?php
/**
 * NetFusion Framework - Enterprise Superconductivity & Quantum Magnetics Fabric
 *
 * Master orchestrator facade managing macroscopic quantum field calculations,
 * Abrikosov flux vortex arrays, Josephson junctions, and SQUID magnetometers.
 *
 * @package    NetFusion\Core\Physics\Superconductivity
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Superconductivity;

use NetFusion\Core\Physics\Superconductivity\Enums\SuperconductorMaterial;
use NetFusion\Core\Physics\Superconductivity\Enums\SuperconductorType;
use NetFusion\Core\Physics\Superconductivity\GinzburgLandau\GinzburgLandauEngine;
use NetFusion\Core\Physics\Superconductivity\Josephson\DCSquidMagnetometer;
use NetFusion\Core\Physics\Superconductivity\Josephson\JosephsonJunction;
use NetFusion\Core\Physics\Superconductivity\Vortex\AbrikosovVortexLattice;

class SuperconductivityManager
{
    protected GinzburgLandauEngine $glEngine;
    protected AbrikosovVortexLattice $vortexLattice;

    public function __construct(
        ?GinzburgLandauEngine $glEngine = null,
        ?AbrikosovVortexLattice $vortexLattice = null
    ) {
        $this->glEngine      = $glEngine ?? new GinzburgLandauEngine();
        $this->vortexLattice = $vortexLattice ?? new AbrikosovVortexLattice($this->glEngine);
    }

    public function getGinzburgLandauEngine(): GinzburgLandauEngine
    {
        return $this->glEngine;
    }

    public function getVortexLattice(): AbrikosovVortexLattice
    {
        return $this->vortexLattice;
    }

    /**
     * Evaluate complete operational state and critical surface of a superconductor material.
     *
     * @return array{
     *     material: string,
     *     type: string,
     *     is_superconducting: bool,
     *     temperature_k: float,
     *     tc_k: float,
     *     applied_field_t: float,
     *     bc1_t: float,
     *     bc2_t: float,
     *     penetration_depth_nm: float,
     *     coherence_length_nm: float,
     *     kappa: float,
     *     state_regime: string
     * }
     */
    public function evaluateState(
        SuperconductorMaterial $mat = SuperconductorMaterial::YBCO,
        float $temperatureK = 77.0,
        float $appliedFieldT = 1.0
    ): array {
        $tc = $mat->criticalTemperature();
        $isBelowTc = ($temperatureK < $tc);

        $bc2 = $this->glEngine->getUpperCriticalField($mat, $temperatureK);
        $bc1 = $this->glEngine->getLowerCriticalField($mat, $temperatureK);
        $lambda = $this->glEngine->getPenetrationDepth($mat, $temperatureK);
        $xi     = $this->glEngine->getCoherenceLength($mat, $temperatureK);
        $kappa  = $this->glEngine->getGinzburgLandauKappa($mat, $temperatureK);

        $isSuper = ($isBelowTc && ($appliedFieldT <= $bc2));

        $regime = 'NORMAL_RESISTIVE';
        if ($isSuper) {
            if ($mat->type() === SuperconductorType::TYPE_I) {
                $regime = 'MEISSNER_STATE';
            } else {
                if ($appliedFieldT <= $bc1) {
                    $regime = 'MEISSNER_STATE';
                } else {
                    $regime = 'ABRIKOSOV_MIXED_VORTEX_STATE';
                }
            }
        }

        return [
            'material'             => $mat->value,
            'type'                 => $mat->type()->value,
            'is_superconducting'   => $isSuper,
            'temperature_k'        => $temperatureK,
            'tc_k'                 => $tc,
            'applied_field_t'      => $appliedFieldT,
            'bc1_t'                => round($bc1, 4),
            'bc2_t'                => round($bc2, 4),
            'penetration_depth_nm' => is_finite($lambda) ? round($lambda * 1e9, 2) : INF,
            'coherence_length_nm'  => round($xi * 1e9, 2),
            'kappa'                => round($kappa, 2),
            'state_regime'         => $regime,
        ];
    }

    /**
     * Create a new Josephson junction.
     */
    public function createJunction(string $id, float $ic = 10e-6, float $rn = 100.0, float $c = 1e-12): JosephsonJunction
    {
        return new JosephsonJunction($id, $ic, $rn, $c);
    }

    /**
     * Create a DC-SQUID magnetometer.
     */
    public function createSquid(float $loopArea = 1e-8, float $loopInductance = 100e-12): DCSquidMagnetometer
    {
        return new DCSquidMagnetometer($loopArea, $loopInductance);
    }
}
