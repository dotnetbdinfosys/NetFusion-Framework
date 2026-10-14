<?php
/**
 * NetFusion Framework - Enterprise Superconductivity & Quantum Magnetics Fabric
 *
 * Josephson tunneling junction and Resistively and Capacitively Shunted Junction (RCSJ) model.
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

class JosephsonJunction
{
    /**
     * Josephson constant K_J = 2*e / h = 1 / Phi_0 in Hz / Volt.
     */
    public const JOSEPHSON_CONSTANT = 4.835978484e14; // ~ 483.6 GHz / mV

    public function __construct(
        protected readonly string $junctionId,
        protected readonly float $criticalCurrent = 10e-6, // I_c in Amperes (default 10 uA)
        protected readonly float $normalResistance = 100.0, // R_n in Ohms
        protected readonly float $capacitance = 1e-12       // C in Farads (default 1 pF)
    ) {
    }

    public function getJunctionId(): string
    {
        return $this->junctionId;
    }

    public function getCriticalCurrent(): float
    {
        return $this->criticalCurrent;
    }

    public function getNormalResistance(): float
    {
        return $this->normalResistance;
    }

    public function getCapacitance(): float
    {
        return $this->capacitance;
    }

    /**
     * DC Josephson supercurrent: I_s = I_c * sin(delta_phi).
     */
    public function getSupercurrent(float $phaseDifferenceRad): float
    {
        return $this->criticalCurrent * sin($phaseDifferenceRad);
    }

    /**
     * AC Josephson oscillation frequency: f_J = (2 * e / h) * V.
     */
    public function getJosephsonFrequency(float $voltageVolts): float
    {
        return self::JOSEPHSON_CONSTANT * abs($voltageVolts);
    }

    /**
     * Stewart-McCumber dimensionless damping parameter:
     * beta_c = (2 * pi * I_c * R_n^2 * C) / Phi_0.
     */
    public function getStewartMcCumberParameter(): float
    {
        $numerator = 2.0 * M_PI * $this->criticalCurrent * pow($this->normalResistance, 2) * $this->capacitance;
        return $numerator / GinzburgLandauEngine::FLUX_QUANTUM;
    }

    /**
     * Whether the junction is overdamped (beta_c < 1, non-hysteretic I-V curve).
     */
    public function isOverdamped(): bool
    {
        return $this->getStewartMcCumberParameter() < 1.0;
    }

    /**
     * Time-averaged DC voltage <V> as function of bias current I in overdamped limit:
     * <V> = 0 for I <= I_c, and <V> = R_n * sqrt(I^2 - I_c^2) for I > I_c.
     */
    public function computeOverdampedVoltage(float $biasCurrent): float
    {
        $i = abs($biasCurrent);
        if ($i <= $this->criticalCurrent) {
            return 0.0;
        }

        $sign = ($biasCurrent >= 0.0) ? 1.0 : -1.0;
        return $sign * $this->normalResistance * sqrt(pow($i, 2) - pow($this->criticalCurrent, 2));
    }

    public function toArray(): array
    {
        return [
            'junction_id'        => $this->junctionId,
            'critical_current_a' => $this->criticalCurrent,
            'normal_resistance'  => $this->normalResistance,
            'capacitance_f'      => $this->capacitance,
            'stewart_mccumber'   => $this->getStewartMcCumberParameter(),
            'is_overdamped'      => $this->isOverdamped(),
        ];
    }
}
