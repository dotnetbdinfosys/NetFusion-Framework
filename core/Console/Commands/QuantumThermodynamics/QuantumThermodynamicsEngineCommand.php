<?php
/**
 * NetFusion Framework - Enterprise Quantum Thermodynamics & Open Quantum Systems Fabric
 *
 * CLI Command: qtherm:engine
 *
 * Runs a Quantum Otto Heat Engine cycle simulation for a two-level qubit
 * working substance between hot and cold thermal reservoirs.
 *
 * Usage:
 *   php netfusion qtherm:engine [temp_cold_k] [temp_hot_k] [omega_cold_ghz] [omega_hot_ghz]
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

class QuantumThermodynamicsEngineCommand extends Command
{
    protected string $name = 'qtherm:engine';
    protected string $description = 'Simulate a Quantum Otto Heat Engine cycle and compute thermodynamic efficiency.';

    public function handle(): int
    {
        $tempCold     = (float) ($this->argument(0) ?? 100.0);
        $tempHot      = (float) ($this->argument(1) ?? 300.0);
        $omegaColdGhz = (float) ($this->argument(2) ?? 1.0);
        $omegaHotGhz  = (float) ($this->argument(3) ?? 2.0);

        $omegaCold = $omegaColdGhz * 1e9;
        $omegaHot  = $omegaHotGhz  * 1e9;

        $engine = new QuantumHeatEngine($omegaCold, $omegaHot, $tempCold, $tempHot);
        $result = $engine->runOttoCycle();

        $this->line('');
        $this->line('=== Quantum Otto Heat Engine Cycle Simulation ===');
        $this->line(sprintf('Cold Bath Temperature (T_c) : %.2f K', $tempCold));
        $this->line(sprintf('Hot Bath Temperature (T_h)  : %.2f K', $tempHot));
        $this->line(sprintf('Cold Stroke Frequency (omega_c): %.3f GHz', $omegaColdGhz));
        $this->line(sprintf('Hot Stroke Frequency (omega_h) : %.3f GHz', $omegaHotGhz));
        $this->line(str_repeat('-', 60));
        $this->line('=== Qubit Thermal Populations ===');
        $this->line(sprintf('Excited pop. n_cold (at T_c, omega_c): %.6f', $result['n_cold']));
        $this->line(sprintf('Excited pop. n_hot  (at T_h, omega_h): %.6f', $result['n_hot']));
        $this->line(str_repeat('-', 60));
        $this->line('=== Energy Budget ===');
        $this->line(sprintf('Heat absorbed from hot bath Q_h  : %+.4e J', $result['q_hot_joules']));
        $this->line(sprintf('Heat rejected to cold bath  Q_c  : %+.4e J', $result['q_cold_joules']));
        $this->line(sprintf('Net Work Output W = -(Q_h + Q_c) : %+.4e J', $result['work_output_joules']));
        $this->line(str_repeat('-', 60));
        $this->line('=== Efficiency & Mode ===');
        $this->line(sprintf('Quantum Otto Efficiency eta  : %.4f (%.2f%%)', $result['otto_efficiency'], $result['otto_efficiency'] * 100.0));
        $this->line(sprintf('Carnot Efficiency Bound eta_C: %.4f (%.2f%%)', $result['carnot_efficiency'], $result['carnot_efficiency'] * 100.0));
        $this->line(sprintf('Otto <= Carnot Verified       : %s', ($result['otto_efficiency'] <= $result['carnot_efficiency'] + 1e-9) ? 'YES' : 'VIOLATION'));
        $this->line(sprintf('Operating Mode               : %s', $result['is_engine'] ? 'HEAT ENGINE [Work Output]' : ($result['is_refrigerator'] ? 'QUANTUM REFRIGERATOR' : 'PASSIVE (no useful work)')));
        if ($result['is_refrigerator']) {
            $this->line(sprintf('Refrigerator COP (|Q_c|/|W|) : %.4f', $result['cop_refrigerator']));
        }
        $this->line(str_repeat('-', 60));
        $this->line('');

        return 0;
    }
}
