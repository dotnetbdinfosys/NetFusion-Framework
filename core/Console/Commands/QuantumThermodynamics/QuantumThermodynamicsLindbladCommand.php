<?php
/**
 * NetFusion Framework - Enterprise Quantum Thermodynamics & Open Quantum Systems Fabric
 *
 * CLI Command: qtherm:lindblad
 *
 * Evolves a qubit open quantum system under the Lindblad GKLS master equation
 * and displays the final density matrix, Von Neumann entropy, and purity.
 *
 * Usage:
 *   php netfusion qtherm:lindblad [omega_ghz] [gamma_mhz] [steps]
 *
 * @package    NetFusion\Core\Console\Commands\QuantumThermodynamics
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\QuantumThermodynamics;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Physics\QuantumThermodynamics\OpenSystem\LindbladSolver;
use NetFusion\Core\Physics\QuantumThermodynamics\State\DensityMatrix;
use NetFusion\Core\Physics\QuantumThermodynamics\Enums\DissipationChannel;

class QuantumThermodynamicsLindbladCommand extends Command
{
    protected string $name = 'qtherm:lindblad';
    protected string $description = 'Evolve a qubit open quantum system under the Lindblad GKLS master equation (RK4 integration).';

    public function handle(): int
    {
        $omegaGhz = (float) ($this->argument(0) ?? 1.0);
        $gammaMhz = (float) ($this->argument(1) ?? 10.0);
        $steps    = (int)   ($this->argument(2) ?? 200);

        $omegaHz = $omegaGhz * 1e9; // Convert GHz -> rad/s
        $gammaHz = $gammaMhz * 1e6;  // Convert MHz -> rad/s
        $dt      = 1e-9;             // 1 ns timestep

        // Dissipation: amplitude damping + dephasing channels
        $dissipators = [
            [DissipationChannel::AMPLITUDE_DAMPING, $gammaHz],
            [DissipationChannel::DEPHASING,         $gammaHz * 0.5],
        ];

        $initial = DensityMatrix::excitedState(); // Start in |1><1|
        $solver  = new LindbladSolver($omegaHz, $dissipators, $dt);
        $final   = $solver->evolve($initial, $steps);

        $totalTime = $steps * $dt * 1e9; // ns

        $this->line('');
        $this->line('=== Lindblad GKLS Master Equation — Qubit Open System Evolution ===');
        $this->line(sprintf('Qubit Frequency omega_0       : %.3f GHz (%.4e rad/s)', $omegaGhz, $omegaHz));
        $this->line(sprintf('Amplitude Damping Rate gamma_1 : %.2f MHz (T1 = %.2f ns)', $gammaMhz, 1e9 / $gammaHz));
        $this->line(sprintf('Dephasing Rate gamma_phi       : %.2f MHz (T2* = %.2f ns)', $gammaMhz * 0.5, 2e9 / $gammaHz));
        $this->line(sprintf('Integration Timestep           : %.2f ns', $dt * 1e9));
        $this->line(sprintf('Total Evolution Time           : %.2f ns (%d steps)', $totalTime, $steps));
        $this->line(str_repeat('-', 60));
        $this->line('=== Initial Density Matrix (Pure |1><1|) ===');
        $this->line(sprintf('  rho_00 (ground pop)  : %.6f', $initial->rho00()));
        $this->line(sprintf('  rho_11 (excited pop) : %.6f', $initial->rho11()));
        $this->line(sprintf('  Purity Tr(rho^2)     : %.6f', $initial->purity()));
        $this->line(sprintf('  Von Neumann Entropy  : %.6f nats', $initial->vonNeumannEntropy()));
        $this->line(str_repeat('-', 60));
        $this->line(sprintf('=== Final Density Matrix (after %.2f ns evolution) ===', $totalTime));
        $this->line(sprintf('  rho_00 (ground pop)  : %.6f', $final->rho00()));
        $this->line(sprintf('  rho_11 (excited pop) : %.6f', $final->rho11()));
        $this->line(sprintf('  Coherence |rho_01|   : %.6e', $final->coherenceMagnitude()));
        $this->line(sprintf('  Purity Tr(rho^2)     : %.6f', $final->purity()));
        $this->line(sprintf('  Von Neumann Entropy  : %.6f nats', $final->vonNeumannEntropy()));
        $this->line(sprintf('  State Type           : %s', $final->isPure() ? 'PURE' : 'MIXED (decoherent)'));
        $this->line(str_repeat('-', 60));
        $this->line('');

        return 0;
    }
}
