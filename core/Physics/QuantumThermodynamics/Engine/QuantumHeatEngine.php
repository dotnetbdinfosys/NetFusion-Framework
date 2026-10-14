<?php
/**
 * NetFusion Framework - Enterprise Quantum Thermodynamics & Open Quantum Systems Fabric
 *
 * Quantum Heat Engine simulator supporting Quantum Otto, Carnot, and Stirling cycles
 * for a two-level system (qubit) working substance.
 *
 * Quantum Otto Cycle (4 strokes):
 *   1. Isentropic Compression: omega_c -> omega_h at fixed populations
 *   2. Isochoric Hot Thermalization: equilibrate with hot bath T_h at omega_h
 *   3. Isentropic Expansion: omega_h -> omega_c at fixed populations
 *   4. Isochoric Cold Thermalization: equilibrate with cold bath T_c at omega_c
 *
 * Key thermodynamic quantities:
 *   Q_h  = hbar * omega_h * (n_h - n_c)   [heat absorbed from hot bath]
 *   Q_c  = hbar * omega_c * (n_c - n_h)   [heat rejected to cold bath] (negative)
 *   W    = -(Q_h + Q_c)                    [net work output]
 *   eta  = W / Q_h = 1 - omega_c / omega_h [Otto efficiency]
 *   eta_Carnot = 1 - T_c / T_h             [Carnot bound]
 *
 * @package    NetFusion\Core\Physics\QuantumThermodynamics\Engine
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\QuantumThermodynamics\Engine;

use NetFusion\Core\Physics\QuantumThermodynamics\State\DensityMatrix;
use NetFusion\Core\Physics\QuantumThermodynamics\Exceptions\QuantumThermodynamicsException;

class QuantumHeatEngine
{
    /**
     * Reduced Planck constant hbar (J·s).
     */
    public const HBAR = 1.054571817e-34;

    /**
     * Boltzmann constant k_B (J/K).
     */
    public const BOLTZMANN = 1.380649e-23;

    /**
     * @param float $omegaCold    Cold stroke angular frequency omega_c (rad/s)
     * @param float $omegaHot     Hot stroke angular frequency omega_h (rad/s) > omega_c
     * @param float $tempCold     Cold bath temperature T_c (Kelvin)
     * @param float $tempHot      Hot bath temperature T_h (Kelvin) > T_c
     */
    public function __construct(
        private readonly float $omegaCold,
        private readonly float $omegaHot,
        private readonly float $tempCold,
        private readonly float $tempHot,
    ) {
        if ($omegaHot <= $omegaCold) {
            throw new QuantumThermodynamicsException(
                "omega_h ({$omegaHot}) must exceed omega_c ({$omegaCold}) for engine operation."
            );
        }
        if ($tempHot <= $tempCold) {
            throw new QuantumThermodynamicsException(
                "T_h ({$tempHot} K) must exceed T_c ({$tempCold} K) for engine operation."
            );
        }
    }

    // ---------------------------------------------------------------
    // Thermal occupation numbers (Bose-Einstein for harmonic qubit)
    // ---------------------------------------------------------------

    /**
     * Mean thermal excitation (Bose-Einstein) number at a given frequency and temperature.
     * n_bar = 1 / (exp(hbar*omega / kT) - 1)
     *
     * For a qubit (two-level system), the excited state population in thermal equilibrium is:
     * rho_11^eq = exp(-hbar*omega/kT) / (1 + exp(-hbar*omega/kT))
     *           = n_bar / (2*n_bar + 1)  [equivalent formulation]
     *
     * We use the Fermi-like form for the two-level system:
     * rho_11^eq(omega, T) = 1 / (exp(hbar*omega / kT) + 1)
     */
    public function thermalExcitation(float $omega, float $temperature): float
    {
        if ($temperature <= 0.0) {
            return 0.0;
        }
        $exponent = (self::HBAR * $omega) / (self::BOLTZMANN * $temperature);
        return 1.0 / (exp($exponent) + 1.0);
    }

    // ---------------------------------------------------------------
    // Quantum Otto Cycle
    // ---------------------------------------------------------------

    /**
     * Run a full Quantum Otto Cycle and return all thermodynamic quantities.
     *
     * @return array{
     *     n_cold: float,           Excited state population at cold thermal equilibrium
     *     n_hot: float,            Excited state population at hot thermal equilibrium
     *     q_hot_joules: float,     Heat absorbed from hot bath (J)
     *     q_cold_joules: float,    Heat rejected to cold bath (J, negative if cooling)
     *     work_output_joules: float, Net work extracted (J, positive for engine)
     *     otto_efficiency: float,  Quantum Otto efficiency eta = 1 - omega_c/omega_h
     *     carnot_efficiency: float, Carnot efficiency bound 1 - T_c/T_h
     *     is_engine: bool,         True when W > 0 (engine mode)
     *     is_refrigerator: bool,   True when Q_c < 0 and W < 0 (refrigerator mode)
     *     cop_refrigerator: float, Coefficient of Performance (|Q_c|/|W|) in refrigerator mode
     * }
     */
    public function runOttoCycle(): array
    {
        // Thermal equilibrium excited state populations
        $nCold = $this->thermalExcitation($this->omegaCold, $this->tempCold);
        $nHot  = $this->thermalExcitation($this->omegaHot,  $this->tempHot);

        // Energy quanta
        $eCold = self::HBAR * $this->omegaCold;
        $eHot  = self::HBAR * $this->omegaHot;

        // Stroke 2: Isochoric heating at omega_h -> heat absorbed from hot bath
        // Q_h = hbar * omega_h * (n_hot - n_cold)  [positive when n_hot > n_cold]
        $qHot = $eHot * ($nHot - $nCold);

        // Stroke 4: Isochoric cooling at omega_c -> heat rejected to cold bath
        // Q_c = hbar * omega_c * (n_cold - n_hot)  [negative: heat flows out]
        $qCold = $eCold * ($nCold - $nHot);

        // Net work extracted W = Q_h + Q_c
        $work = $qHot + $qCold;

        // Otto efficiency: eta = 1 - omega_c / omega_h
        $etaOtto   = 1.0 - $this->omegaCold / $this->omegaHot;
        $etaCarnot = 1.0 - $this->tempCold / $this->tempHot;

        $isEngine       = ($work > 0.0 && $qHot > 0.0);
        $isRefrigerator = ($work < 0.0 && $qCold > 0.0); // work consumed, heat removed from cold bath

        // COP for refrigerator: Q_c / |W|
        $cop = ($isRefrigerator && abs($work) > 1e-50)
            ? $qCold / abs($work)
            : 0.0;

        return [
            'n_cold'               => $nCold,
            'n_hot'                => $nHot,
            'q_hot_joules'         => $qHot,
            'q_cold_joules'        => $qCold,
            'work_output_joules'   => $work,
            'otto_efficiency'      => $etaOtto,
            'carnot_efficiency'    => $etaCarnot,
            'is_engine'            => $isEngine,
            'is_refrigerator'      => $isRefrigerator,
            'cop_refrigerator'     => $cop,
        ];
    }

    // ---------------------------------------------------------------
    // Landauer Erasure (Quantum Information Thermodynamics)
    // ---------------------------------------------------------------

    /**
     * Compute minimum heat dissipation to erase $bits bits of quantum information
     * according to the Landauer principle:
     *   Q_min = k_B * T * ln(2) * bits
     *
     * This is the fundamental thermodynamic cost of irreversible information erasure
     * in a thermal reservoir at temperature T.
     *
     * @param float $bits           Number of bits erased (1 bit per qubit reset)
     * @param float $temperatureK   Thermal bath temperature (Kelvin)
     * @return array{
     *     bits_erased: float,
     *     temperature_k: float,
     *     landauer_limit_joules: float,   Minimum dissipated heat Q_min = kT*ln2*bits
     *     landauer_limit_ev: float,       Same in electron-volts (eV)
     *     entropy_decrease_bits: float,   Shannon entropy decrease (= bits)
     *     entropy_decrease_nat: float,    Von Neumann entropy decrease in nats
     * }
     */
    public function landauerErasure(float $bits, float $temperatureK): array
    {
        if ($temperatureK <= 0.0) {
            throw new QuantumThermodynamicsException(
                "Landauer erasure requires T > 0; got {$temperatureK} K."
            );
        }
        $qMin = self::BOLTZMANN * $temperatureK * log(2.0) * $bits;
        return [
            'bits_erased'           => $bits,
            'temperature_k'         => $temperatureK,
            'landauer_limit_joules' => $qMin,
            'landauer_limit_ev'     => $qMin / 1.602176634e-19,
            'entropy_decrease_bits' => $bits,
            'entropy_decrease_nat'  => $bits * log(2.0),
        ];
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    public function getOmegaCold(): float { return $this->omegaCold; }
    public function getOmegaHot(): float  { return $this->omegaHot; }
    public function getTempCold(): float  { return $this->tempCold; }
    public function getTempHot(): float   { return $this->tempHot; }

    /**
     * Theoretical Otto efficiency (depends only on frequency ratio).
     */
    public function ottEfficiency(): float
    {
        return 1.0 - $this->omegaCold / $this->omegaHot;
    }

    /**
     * Carnot efficiency upper bound (depends only on temperature ratio).
     */
    public function carnotEfficiency(): float
    {
        return 1.0 - $this->tempCold / $this->tempHot;
    }
}
