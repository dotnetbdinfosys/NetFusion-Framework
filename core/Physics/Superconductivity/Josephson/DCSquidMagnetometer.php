<?php
/**
 * NetFusion Framework - Enterprise Superconductivity & Quantum Magnetics Fabric
 *
 * Direct-Current Superconducting Quantum Interference Device (DC-SQUID) magnetometer.
 *
 * Models macroscopic quantum interference and periodic flux-to-voltage modulation V(Phi).
 *
 * @package    NetFusion\Core\Physics\Superconductivity\Josephson
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Superconductivity\Josephson;

use NetFusion\Core\Physics\Superconductivity\GinzburgLandau\GinzburgLandauEngine;

class DCSquidMagnetometer
{
    protected JosephsonJunction $junction1;
    protected JosephsonJunction $junction2;

    public function __construct(
        protected readonly float $loopAreaM2 = 1e-8,       // Loop area in m^2 (e.g. 100 um x 100 um = 1e-8 m^2)
        protected readonly float $loopInductanceH = 100e-12, // Loop inductance L in Henry (default 100 pH)
        ?JosephsonJunction $junction1 = null,
        ?JosephsonJunction $junction2 = null
    ) {
        $this->junction1 = $junction1 ?? new JosephsonJunction('sq_j1', 10e-6, 50.0);
        $this->junction2 = $junction2 ?? new JosephsonJunction('sq_j2', 10e-6, 50.0);
    }

    public function getLoopArea(): float
    {
        return $this->loopAreaM2;
    }

    public function getLoopInductance(): float
    {
        return $this->loopInductanceH;
    }

    /**
     * Compute magnetic flux Phi = B * A in Weber (T * m^2).
     */
    public function computeMagneticFlux(float $magneticFieldTesla): float
    {
        return $magneticFieldTesla * $this->loopAreaM2;
    }

    /**
     * SQUID screening parameter beta_L = 2 * L * I_c / Phi_0.
     */
    public function getScreeningParameter(): float
    {
        $ic0 = $this->junction1->getCriticalCurrent();
        return (2.0 * $this->loopInductanceH * $ic0) / GinzburgLandauEngine::FLUX_QUANTUM;
    }

    /**
     * Maximum interference supercurrent as function of magnetic flux Phi:
     * I_c(Phi) = 2 * I_c0 * |cos(pi * Phi / Phi_0)|.
     */
    public function getMaximumSupercurrent(float $fluxWebers): float
    {
        $ic0 = ($this->junction1->getCriticalCurrent() + $this->junction2->getCriticalCurrent()) * 0.5;
        $phase = (M_PI * $fluxWebers) / GinzburgLandauEngine::FLUX_QUANTUM;

        return 2.0 * $ic0 * abs(cos($phase));
    }

    /**
     * Output DC voltage V(Phi) under constant bias current I_bias in the overdamped limit:
     * V = R_eff * sqrt( (I_bias / 2)^2 - (I_c0 * |cos(pi*Phi/Phi_0)|)^2 ).
     */
    public function getOutputVoltage(float $fluxWebers, ?float $biasCurrent = null): float
    {
        $ic0 = ($this->junction1->getCriticalCurrent() + $this->junction2->getCriticalCurrent()) * 0.5;
        $rEff = ($this->junction1->getNormalResistance() * $this->junction2->getNormalResistance())
            / ($this->junction1->getNormalResistance() + $this->junction2->getNormalResistance());

        // Default bias slightly above peak critical current (e.g. 2.1 * I_c0)
        $ib = $biasCurrent ?? (2.1 * $ic0);

        $icPhi = $this->getMaximumSupercurrent($fluxWebers);

        if ($ib <= $icPhi) {
            return 0.0;
        }

        return $rEff * sqrt(pow($ib, 2) - pow($icPhi, 2));
    }

    /**
     * Flux-to-voltage transfer sensitivity V_Phi = |dV / dPhi| in Volts / Phi_0.
     */
    public function getTransferFunction(float $biasCurrent): float
    {
        $rEff = ($this->junction1->getNormalResistance() * $this->junction2->getNormalResistance())
            / ($this->junction1->getNormalResistance() + $this->junction2->getNormalResistance());

        // Typical maximum sensitivity occurs at Phi = (2n + 1) * Phi_0 / 4: V_Phi ~ R_eff / Phi_0
        return $rEff / GinzburgLandauEngine::FLUX_QUANTUM;
    }
}
