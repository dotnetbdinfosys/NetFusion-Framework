<?php
/**
 * NetFusion Framework - Enterprise Synthetic Biology & BioCAD CLI
 *
 * Command to simulate synthetic gene circuit dynamics using 4th-order Runge-Kutta integration.
 *
 * @package    NetFusion\Core\Console\Commands\Bio
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Bio;

use NetFusion\Core\Console\Command;

class BioCadCircuitCommand extends Command
{
    protected string $name = 'biocad:circuit';

    protected string $signature = 'biocad:circuit {--type=toggle : Circuit type (toggle, repressilator)} {--steps=10 : Simulation steps to display}';

    protected string $description = 'Simulate dynamic trajectories of synthetic gene circuits (Toggle Switch, Repressilator)';

    public function handle(): int
    {
        $manager = biocad();

        $type  = (string) ($this->option('type') ?: 'toggle');
        $steps = (int) ($this->option('steps') ?: 10);

        $this->info("Simulating Synthetic Gene Circuit Kinetics...");

        if (strtolower($type) === 'repressilator') {
            $this->line("Circuit Model : Elowitz-Leibler Repressilator (Cyclic 3-gene feedback oscillator)");
            $traj = $manager->simulateRepressilator();

            $this->info("=== Dynamic Trajectory (First N Steps) ===");
            $this->line(sprintf("%-8s | %-12s | %-12s | %-12s", "Time (t)", "p1 (LacI)", "p2 (TetR)", "p3 (cI)"));
            $this->line("--------------------------------------------------");

            $sample = array_slice($traj, 0, $steps);
            foreach ($sample as $pt) {
                $t = $pt['t'];
                $s = $pt['state'];
                $this->line(sprintf("%-8.2f | %-12.4f | %-12.4f | %-12.4f", $t, $s['p1'] ?? 0.0, $s['p2'] ?? 0.0, $s['p3'] ?? 0.0));
            }
        } else {
            $this->line("Circuit Model : Gardner et al. Genetic Toggle Switch (Mutual bistable repressor)");
            $traj = $manager->simulateToggle(2.0, 0.2);

            $this->info("=== Dynamic Trajectory (First N Steps) ===");
            $this->line(sprintf("%-8s | %-14s | %-14s", "Time (t)", "Protein U", "Protein V"));
            $this->line("--------------------------------------------------");

            $sample = array_slice($traj, 0, $steps);
            foreach ($sample as $pt) {
                $t = $pt['t'];
                $s = $pt['state'];
                $this->line(sprintf("%-8.2f | %-14.4f | %-14.4f", $t, $s['u'] ?? 0.0, $s['v'] ?? 0.0));
            }
        }

        $this->line("--------------------------------------------------");
        $this->info("Simulation completed successfully.");

        return 0;
    }
}
