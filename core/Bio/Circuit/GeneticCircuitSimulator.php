<?php
/**
 * NetFusion Framework - Enterprise Synthetic Biology & BioCAD Fabric
 *
 * 4th-order Runge-Kutta (RK4) ordinary differential equation simulator for synthetic gene circuits.
 *
 * @package    NetFusion\Core\Bio\Circuit
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Bio\Circuit;

class GeneticCircuitSimulator
{
    /**
     * Run Runge-Kutta 4th-order (RK4) numerical ODE integration.
     *
     * @param callable(float, array<string, float>): array<string, float> $derivatives
     * @param array<string, float> $y0 Initial state vector
     * @return array<int, array{t: float, state: array<string, float>}>
     */
    public function integrateRk4(
        callable $derivatives,
        array $y0,
        float $tMax = 10.0,
        float $dt = 0.1
    ): array {
        $trajectory = [];
        $t = 0.0;
        $y = $y0;

        $trajectory[] = ['t' => round($t, 3), 'state' => $y];

        while ($t < $tMax - 1e-9) {
            $h = min($dt, $tMax - $t);

            // k1 = f(t, y)
            $k1 = $derivatives($t, $y);

            // k2 = f(t + h/2, y + h/2 * k1)
            $y_k2 = [];
            foreach ($y as $var => $val) {
                $y_k2[$var] = $val + 0.5 * $h * ($k1[$var] ?? 0.0);
            }
            $k2 = $derivatives($t + 0.5 * $h, $y_k2);

            // k3 = f(t + h/2, y + h/2 * k2)
            $y_k3 = [];
            foreach ($y as $var => $val) {
                $y_k3[$var] = $val + 0.5 * $h * ($k2[$var] ?? 0.0);
            }
            $k3 = $derivatives($t + 0.5 * $h, $y_k3);

            // k4 = f(t + h, y + h * k3)
            $y_k4 = [];
            foreach ($y as $var => $val) {
                $y_k4[$var] = $val + $h * ($k3[$var] ?? 0.0);
            }
            $k4 = $derivatives($t + $h, $y_k4);

            // y_(n+1) = y + (h/6) * (k1 + 2*k2 + 2*k3 + k4)
            foreach ($y as $var => $val) {
                $dy = ($h / 6.0) * (($k1[$var] ?? 0.0) + 2.0 * ($k2[$var] ?? 0.0) + 2.0 * ($k3[$var] ?? 0.0) + ($k4[$var] ?? 0.0));
                $y[$var] = max(0.0, $val + $dy); // Protein/mRNA concentrations cannot be negative
            }

            $t += $h;
            $trajectory[] = ['t' => round($t, 3), 'state' => $y];
        }

        return $trajectory;
    }

    /**
     * Simulate Gardner et al. (2000) Genetic Toggle Switch.
     *
     * du/dt = alpha1 / (1 + v^beta) - u
     * dv/dt = alpha2 / (1 + u^gamma) - v
     *
     * @return array<int, array{t: float, state: array{u: float, v: float}}>
     */
    public function simulateToggleSwitch(
        float $initU = 2.0,
        float $initV = 0.2,
        float $alpha1 = 10.0,
        float $alpha2 = 10.0,
        float $beta = 2.0,
        float $gamma = 2.0,
        float $tMax = 10.0,
        float $dt = 0.1
    ): array {
        $derivatives = function (float $t, array $state) use ($alpha1, $alpha2, $beta, $gamma) {
            $u = $state['u'] ?? 0.0;
            $v = $state['v'] ?? 0.0;

            $du = ($alpha1 / (1.0 + pow($v, $beta))) - $u;
            $dv = ($alpha2 / (1.0 + pow($u, $gamma))) - $v;

            return ['u' => $du, 'v' => $dv];
        };

        return $this->integrateRk4($derivatives, ['u' => $initU, 'v' => $initV], $tMax, $dt);
    }

    /**
     * Simulate Elowitz & Leibler (2000) Synthetic Repressilator Oscillator.
     *
     * Three genes (LacI -> TetR -> cI -> LacI).
     *
     * @return array<int, array{t: float, state: array{p1: float, p2: float, p3: float}}>
     */
    public function simulateRepressilator(
        float $alpha = 50.0,
        float $alpha0 = 0.2,
        float $n = 2.0,
        float $tMax = 20.0,
        float $dt = 0.2
    ): array {
        $y0 = ['p1' => 0.0, 'p2' => 2.0, 'p3' => 8.0];

        $derivatives = function (float $t, array $state) use ($alpha, $alpha0, $n) {
            $p1 = $state['p1'] ?? 0.0;
            $p2 = $state['p2'] ?? 0.0;
            $p3 = $state['p3'] ?? 0.0;

            // Cyclic mutual repression
            $dp1 = ($alpha / (1.0 + pow($p3, $n))) + $alpha0 - $p1;
            $dp2 = ($alpha / (1.0 + pow($p1, $n))) + $alpha0 - $p2;
            $dp3 = ($alpha / (1.0 + pow($p2, $n))) + $alpha0 - $p3;

            return ['p1' => $dp1, 'p2' => $dp2, 'p3' => $dp3];
        };

        return $this->integrateRk4($derivatives, $y0, $tMax, $dt);
    }
}
