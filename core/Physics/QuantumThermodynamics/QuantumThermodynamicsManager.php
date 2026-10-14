<?php
/**
 * NetFusion Framework - Enterprise Quantum Thermodynamics & Open Quantum Systems Fabric
 *
 * Master facade orchestrating all quantum thermodynamics subsystems:
 *   - DensityMatrix (qubit state representation)
 *   - LindbladSolver (open quantum systems / GKLS master equation)
 *   - QuantumHeatEngine (Otto cycle, Landauer erasure)
 *
 * @package    NetFusion\Core\Physics\QuantumThermodynamics
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\QuantumThermodynamics;

use NetFusion\Core\Physics\QuantumThermodynamics\State\DensityMatrix;
use NetFusion\Core\Physics\QuantumThermodynamics\OpenSystem\LindbladSolver;
use NetFusion\Core\Physics\QuantumThermodynamics\Engine\QuantumHeatEngine;
use NetFusion\Core\Physics\QuantumThermodynamics\Enums\DissipationChannel;

class QuantumThermodynamicsManager
{
    /**
     * Solve open quantum system dynamics using the Lindblad master equation.
     *
     * @param float $omega_hz    Qubit transition frequency (rad/s)
     * @param array $dissipators [[DissipationChannel, float $rate_hz], ...]
     * @param float $dt          Integration timestep (seconds)
     * @param int   $steps       Number of RK4 integration steps
     * @param array $initialRho  Optional initial state [rho00, rho11, cohRe, cohIm]
     * @return DensityMatrix     Final evolved density matrix
     */
    public function lindblad(
        float $omega_hz,
        array $dissipators,
        float $dt = 1e-9,
        int $steps = 100,
        array $initialRho = [1.0, 0.0, 0.0, 0.0]
    ): DensityMatrix {
        $solver = new LindbladSolver($omega_hz, $dissipators, $dt);
        $initial = new DensityMatrix(...$initialRho);
        return $solver->evolve($initial, $steps);
    }

    /**
     * Evolve an open quantum system to steady state.
     *
     * @param float $omega_hz    Qubit transition frequency (rad/s)
     * @param array $dissipators [[DissipationChannel, float $rate_hz], ...]
     * @param float $dt          Integration timestep
     * @return DensityMatrix     Steady-state density matrix
     */
    public function lindblad_steady_state(
        float $omega_hz,
        array $dissipators,
        float $dt = 1e-9
    ): DensityMatrix {
        $solver  = new LindbladSolver($omega_hz, $dissipators, $dt);
        $initial = DensityMatrix::excitedState();
        return $solver->steadyState($initial);
    }

    /**
     * Run a Quantum Otto Heat Engine cycle.
     *
     * @param float $omegaCold  Cold frequency omega_c (rad/s)
     * @param float $omegaHot   Hot frequency omega_h (rad/s)
     * @param float $tempCold   Cold bath temperature T_c (K)
     * @param float $tempHot    Hot bath temperature T_h (K)
     * @return array            Full Otto cycle results
     */
    public function otto(
        float $omegaCold,
        float $omegaHot,
        float $tempCold,
        float $tempHot
    ): array {
        $engine = new QuantumHeatEngine($omegaCold, $omegaHot, $tempCold, $tempHot);
        return $engine->runOttoCycle();
    }

    /**
     * Compute Landauer information erasure energetic cost.
     *
     * @param float $bits         Number of bits erased
     * @param float $temperatureK Bath temperature (K)
     * @return array              Landauer dissipation results
     */
    public function landauer(float $bits, float $temperatureK): array
    {
        $engine = new QuantumHeatEngine(1e9, 2e9, 100.0, 300.0); // dummy engine for Landauer
        return $engine->landauerErasure($bits, $temperatureK);
    }

    /**
     * Create a thermal equilibrium density matrix at temperature T for a given qubit frequency.
     *
     * @param float $omega_hz       Qubit frequency (rad/s)
     * @param float $temperatureK   Temperature (K)
     * @return DensityMatrix
     */
    public function thermalState(float $omega_hz, float $temperatureK): DensityMatrix
    {
        return DensityMatrix::thermalState(LindbladSolver::HBAR * $omega_hz, $temperatureK);
    }
}
