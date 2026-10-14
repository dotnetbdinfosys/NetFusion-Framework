<?php
/**
 * NetFusion Framework - Enterprise Computational Fluid Dynamics CLI
 *
 * Command to execute Lid-Driven Cavity Flow simulation and render velocity profile.
 *
 * @package    NetFusion\Core\Console\Commands\Lbm
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Lbm;

use NetFusion\Core\Console\Command;

class LbmCavityCommand extends Command
{
    protected string $name = 'lbm:cavity';

    protected string $signature = 'lbm:cavity {--size=30 : Grid dimension N x N} {--steps=40 : Timesteps to simulate} {--lid=0.08 : Lid speed} {--tau=0.6 : Relaxation time}';

    protected string $description = 'Simulate 2D Lid-Driven Cavity fluid circulation using Lattice Boltzmann D2Q9';

    public function handle(): int
    {
        $manager = lbm();

        $size  = (int) ($this->option('size') ?: 30);
        $steps = (int) ($this->option('steps') ?: 40);
        $lid   = (float) ($this->option('lid') ?: 0.08);
        $tau   = (float) ($this->option('tau') ?: 0.6);

        $manager->setTau($tau);
        $grid = $manager->createGrid($size, $size);
        $grid->setupLidDrivenCavity($lid);

        $this->info("Initializing D2Q9 Lid-Driven Cavity Flow Simulation...");
        $this->line(sprintf("Grid Size   : %dx%d (Total Nodes: %d)", $size, $size, $size * $size));
        $this->line(sprintf("Lid Speed   : %.4f | Relaxation Tau: %.2f", $lid, $tau));
        $this->line(sprintf("Viscosity nu: %.4f", $manager->getViscosity()));

        $t0 = microtime(true);
        $manager->step($steps);
        $elapsed = round(microtime(true) - $t0, 4);

        $field = $manager->extractField();
        $re = $field->getReynoldsNumber((float) $size, $manager->getViscosity());

        $this->info("=== Simulation Completed ===");
        $this->line(sprintf("Simulated Steps : %d (in %.4fs)", $steps, $elapsed));
        $this->line(sprintf("Max Flow Speed  : %.4f", $field->getMaxSpeed()));
        $this->line(sprintf("Reynolds Number : Re = %.2f", $re));
        $this->line(sprintf("Average Density : %.4f", $field->getAvgDensity()));
        $this->line("");
        $this->line("Velocity Magnitude ASCII Heatmap (Top = Moving Lid):");
        $this->line("--------------------------------------------------");
        echo $field->renderAscii(min(45, $size), min(15, $size));
        $this->line("--------------------------------------------------");

        return 0;
    }
}
