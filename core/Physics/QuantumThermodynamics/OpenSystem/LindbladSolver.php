<?php
/**
 * NetFusion Framework - Enterprise Quantum Thermodynamics & Open Quantum Systems Fabric
 *
 * Lindblad GKLS Master Equation solver for qubit open quantum systems.
 *
 * Evolves the density matrix rho(t) under the Gorini-Kossakowski-Lindblad-Sudarshan
 * (GKLS) master equation using 4th-order Runge-Kutta (RK4) numerical integration:
 *
 *   d_rho/dt = -i/hbar [H, rho] + sum_k gamma_k (L_k rho L_k† - 1/2 {L_k†L_k, rho})
 *
 * Supported dissipation channels for a qubit:
 *   - Amplitude damping (sigma_-): spontaneous emission / T1 decay
 *   - Dephasing (sigma_z/2): pure phase decoherence / T2* decay
 *   - Thermal pumping (sigma_+): thermal excitation from hot bath
 *
 * @package    NetFusion\Core\Physics\QuantumThermodynamics\OpenSystem
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\QuantumThermodynamics\OpenSystem;

use NetFusion\Core\Physics\QuantumThermodynamics\State\DensityMatrix;
use NetFusion\Core\Physics\QuantumThermodynamics\Enums\DissipationChannel;

class LindbladSolver
{
    /**
     * Reduced Planck constant hbar (J·s).
     */
    public const HBAR = 1.054571817e-34;

    /**
     * @param float $hamiltonian_hz   Qubit transition frequency omega_0 in rad/s
     *                                (sets H = hbar * omega_0 / 2 * sigma_z for a 2-level system)
     * @param array $dissipators      Array of [DissipationChannel, float $gamma_rate_hz]
     *                                gamma in rad/s (1/T1 or 1/T2 or thermal pumping rate)
     * @param float $dt               Integration timestep in seconds (default 1 ns)
     */
    public function __construct(
        private readonly float $hamiltonian_hz,
        private readonly array $dissipators = [],
        private readonly float $dt = 1e-9
    ) {
    }

    /**
     * Evolve the density matrix rho(t=0) forward by $steps integration steps.
     *
     * Returns the final DensityMatrix after $steps * $dt seconds of evolution.
     *
     * @param DensityMatrix $initialState  Initial density matrix at t=0
     * @param int           $steps         Number of RK4 integration steps
     * @return DensityMatrix               Final density matrix after evolution
     */
    public function evolve(DensityMatrix $initialState, int $steps = 100): DensityMatrix
    {
        // Internal state: [rho00, rho11, cohRe, cohIm]
        $state = [
            $initialState->rho00(),
            $initialState->rho11(),
            $initialState->element(0, 1)[0],
            $initialState->element(0, 1)[1],
        ];

        for ($i = 0; $i < $steps; $i++) {
            $state = $this->rk4Step($state);
            // Re-normalize after each step to prevent floating point drift
            $trace = $state[0] + $state[1];
            if ($trace > 1e-15) {
                $state[0] /= $trace;
                $state[1] /= $trace;
            }
        }

        return new DensityMatrix(
            max(0.0, min(1.0, $state[0])),
            max(0.0, min(1.0, $state[1])),
            $state[2],
            $state[3]
        );
    }

    /**
     * Evolve to steady-state (the fixed-point of d_rho/dt = 0) by iterating
     * until the change per step is below tolerance.
     *
     * @param DensityMatrix $initialState
     * @param float         $tolerance    Convergence threshold in rho change
     * @param int           $maxSteps     Maximum allowed steps before giving up
     */
    public function steadyState(
        DensityMatrix $initialState,
        float $tolerance = 1e-12,
        int $maxSteps = 100000
    ): DensityMatrix {
        $state = [
            $initialState->rho00(),
            $initialState->rho11(),
            $initialState->element(0, 1)[0],
            $initialState->element(0, 1)[1],
        ];

        for ($i = 0; $i < $maxSteps; $i++) {
            $next = $this->rk4Step($state);
            $trace = $next[0] + $next[1];
            if ($trace > 1e-15) {
                $next[0] /= $trace;
                $next[1] /= $trace;
            }

            // Check convergence: max element change < tolerance
            $maxDelta = max(
                abs($next[0] - $state[0]),
                abs($next[1] - $state[1]),
                abs($next[2] - $state[2]),
                abs($next[3] - $state[3])
            );
            $state = $next;
            if ($maxDelta < $tolerance) {
                break;
            }
        }

        return new DensityMatrix(
            max(0.0, min(1.0, $state[0])),
            max(0.0, min(1.0, $state[1])),
            $state[2],
            $state[3]
        );
    }

    // ---------------------------------------------------------------
    // Private: RK4 integration
    // ---------------------------------------------------------------

    /**
     * Single 4th-order Runge-Kutta step for d_rho/dt = L(rho).
     *
     * @param  float[] $y  [rho00, rho11, cohRe, cohIm]
     * @return float[]     Next state [rho00', rho11', cohRe', cohIm']
     */
    private function rk4Step(array $y): array
    {
        $h = $this->dt;
        $k1 = $this->drhodt($y);
        $k2 = $this->drhodt($this->addScaled($y, $k1, $h / 2));
        $k3 = $this->drhodt($this->addScaled($y, $k2, $h / 2));
        $k4 = $this->drhodt($this->addScaled($y, $k3, $h));

        return [
            $y[0] + ($h / 6.0) * ($k1[0] + 2 * $k2[0] + 2 * $k3[0] + $k4[0]),
            $y[1] + ($h / 6.0) * ($k1[1] + 2 * $k2[1] + 2 * $k3[1] + $k4[1]),
            $y[2] + ($h / 6.0) * ($k1[2] + 2 * $k2[2] + 2 * $k3[2] + $k4[2]),
            $y[3] + ($h / 6.0) * ($k1[3] + 2 * $k2[3] + 2 * $k3[3] + $k4[3]),
        ];
    }

    /**
     * Compute d_rho/dt = Liouvillian (Hamiltonian commutator + Lindblad dissipators).
     *
     * State encoding: y = [rho00, rho11, cohRe, cohIm]
     *
     * Hamiltonian: H = hbar*omega/2 * sigma_z
     *   sigma_z = [[1,0],[0,-1]]
     *   [H, rho]_{01} = H_00*rho01 - rho01*H_11 = hbar*omega/2*(1-(-1))*rho01 = hbar*omega*rho01
     *   => d(cohRe+i*cohIm)/dt|_unitary = -i * omega * (cohRe + i*cohIm)
     *                                    = omega * cohIm + i*(-omega * cohRe)
     * Populations are unchanged by a diagonal Hamiltonian commutator.
     *
     * @param  float[] $y
     * @return float[] d_rho/dt as [dRho00, dRho11, dCohRe, dCohIm]
     */
    private function drhodt(array $y): array
    {
        [$r00, $r11, $cRe, $cIm] = $y;
        $omega = $this->hamiltonian_hz;

        // Hamiltonian commutator contributions to off-diagonal elements:
        // d(rho_01)/dt|_H = -i * omega * rho_01
        // d(cohRe)/dt = omega * cohIm
        // d(cohIm)/dt = -omega * cohRe
        $dCohReH = $omega * $cIm;
        $dCohImH = -$omega * $cRe;
        $dR00H   = 0.0;
        $dR11H   = 0.0;

        // Lindblad dissipator contributions
        $dR00L   = 0.0;
        $dR11L   = 0.0;
        $dCohReL = 0.0;
        $dCohImL = 0.0;

        foreach ($this->dissipators as [$channel, $gamma]) {
            $gamma = (float)$gamma;
            switch ($channel) {
                case DissipationChannel::AMPLITUDE_DAMPING:
                    // L = sigma_- = |0><1|: decays excited -> ground
                    // d(rho00)/dt += gamma * rho11
                    // d(rho11)/dt -= gamma * rho11
                    // d(rho01)/dt -= gamma/2 * rho01
                    $dR00L   += $gamma * $r11;
                    $dR11L   -= $gamma * $r11;
                    $dCohReL -= ($gamma / 2.0) * $cRe;
                    $dCohImL -= ($gamma / 2.0) * $cIm;
                    break;

                case DissipationChannel::DEPHASING:
                    // L = sigma_z / 2: pure dephasing
                    // Populations unchanged; coherences decay:
                    // d(rho01)/dt -= gamma * rho01
                    $dCohReL -= $gamma * $cRe;
                    $dCohImL -= $gamma * $cIm;
                    break;

                case DissipationChannel::THERMAL_PUMPING:
                    // L = sigma_+ = |1><0|: excites ground -> excited
                    // d(rho00)/dt -= gamma * rho00
                    // d(rho11)/dt += gamma * rho00
                    // d(rho01)/dt -= gamma/2 * rho01
                    $dR00L   -= $gamma * $r00;
                    $dR11L   += $gamma * $r00;
                    $dCohReL -= ($gamma / 2.0) * $cRe;
                    $dCohImL -= ($gamma / 2.0) * $cIm;
                    break;
            }
        }

        return [
            $dR00H   + $dR00L,
            $dR11H   + $dR11L,
            $dCohReH + $dCohReL,
            $dCohImH + $dCohImL,
        ];
    }

    /**
     * Helper: y + scale * dy element-wise.
     *
     * @param float[] $y
     * @param float[] $dy
     * @param float   $scale
     * @return float[]
     */
    private function addScaled(array $y, array $dy, float $scale): array
    {
        return [
            $y[0] + $scale * $dy[0],
            $y[1] + $scale * $dy[1],
            $y[2] + $scale * $dy[2],
            $y[3] + $scale * $dy[3],
        ];
    }
}
