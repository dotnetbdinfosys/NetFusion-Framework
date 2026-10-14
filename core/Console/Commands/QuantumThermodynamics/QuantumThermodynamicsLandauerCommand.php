<?php
/**
 * NetFusion Framework - Enterprise Quantum Thermodynamics & Open Quantum Systems Fabric
 *
 * CLI Command: qtherm:landauer
 *
 * Computes the Landauer minimum heat dissipation for erasing classical bits
 * in a quantum/thermal environment: Q_min = k_B * T * ln(2) * bits.
 *
 * Usage:
 *   php netfusion qtherm:landauer [bits] [temperature_k]
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
use NetFusion\Core\Physics\QuantumThermodynamics\Engine\QuantumHeatEngine;

class QuantumThermodynamicsLandauerCommand extends Command
{
    protected string $name = 'qtherm:landauer';
    protected string $description = 'Compute Landauer erasure minimum heat dissipation: Q_min = k_B * T * ln(2) * bits.';

    public function handle(): int
    {
        $bits = (float) ($this->argument(0) ?? 1.0);
        $temp = (float) ($this->argument(1) ?? 300.0);

        $engine = new QuantumHeatEngine(1e9, 2e9, 100.0, 300.0);
        $result = $engine->landauerErasure($bits, $temp);

        $this->line('');
        $this->line('=== Landauer Erasure Principle — Quantum Information Thermodynamics ===');
        $this->line(sprintf('Bits Erased (n)           : %.1f bits', $result['bits_erased']));
        $this->line(sprintf('Thermal Bath Temperature  : %.2f K', $result['temperature_k']));
        $this->line(str_repeat('-', 60));
        $this->line('=== Minimum Dissipated Heat ===');
        $this->line(sprintf('Q_min = n * k_B * T * ln(2)'));
        $this->line(sprintf('Q_min = %.4e Joules', $result['landauer_limit_joules']));
        $this->line(sprintf('Q_min = %.6f meV  (%.4e eV)', $result['landauer_limit_ev'] * 1000.0, $result['landauer_limit_ev']));
        $this->line(str_repeat('-', 60));
        $this->line('=== Information & Entropy ===');
        $this->line(sprintf('Shannon Entropy Decrease  : %.4f bits', $result['entropy_decrease_bits']));
        $this->line(sprintf('Von Neumann Entropy Delta : %.6f nats', $result['entropy_decrease_nat']));
        $this->line(sprintf('Thermodynamic Entropy Prod: %.4e J/K', $result['landauer_limit_joules'] / max($temp, 1e-9)));
        $this->line(str_repeat('-', 60));
        $this->line(sprintf('At 300K: 1-bit erasure costs %.4f meV (%.4e J)', 300 * 1.380649e-23 * log(2) / 1.602176634e-22, 300 * 1.380649e-23 * log(2)));
        $this->line('');

        return 0;
    }
}
