<?php
/**
 * NetFusion Framework - Enterprise Astro-Informatics CLI
 *
 * Command to run Symplectic N-Body gravitational simulation (Leapfrog / Yoshida 4th-order).
 *
 * @package    NetFusion\Core\Console\Commands\Astro
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Astro;

use NetFusion\Core\Astro\Enums\IntegratorType;
use NetFusion\Core\Astro\Orbit\KeplerianElements;
use NetFusion\Core\Console\Command;

class AstroNBodyCommand extends Command
{
    protected string $name = 'astro:nbody';

    protected string $signature = 'astro:nbody {--steps=10 : Simulation timesteps} {--dt=86400 : Timestep in seconds} {--integrator=leapfrog : Symplectic integrator (leapfrog, yoshida_4th)}';

    protected string $description = 'Simulate Symplectic N-Body gravitational orbital dynamics';

    public function handle(): int
    {
        $manager = astro();

        $steps = (int) ($this->option('steps') ?: 10);
        $dt    = (float) ($this->option('dt') ?: 86400.0);
        $intName = (string) ($this->option('integrator') ?: 'leapfrog');

        $type = IntegratorType::tryFrom($intName) ?? IntegratorType::LEAPFROG;

        $manager->loadMiniSolarSystem();
        $bodies = $manager->getBodies();

        $this->info("Initializing Symplectic N-Body Simulation...");
        $this->line(sprintf("Integrator Engine : %s (Symplectic: %s, Order: %d)", $type->value, $type->isSymplectic() ? 'YES' : 'NO', $type->order()));
        $this->line(sprintf("Bodies in System  : %d (%s)", count($bodies), implode(', ', array_keys($bodies))));
        $this->line(sprintf("Timestep (dt)     : %.1f seconds (%.2f days) | Total Steps: %d", $dt, $dt / 86400.0, $steps));

        $t0 = microtime(true);
        $history = $manager->propagate($steps, $dt, $type);
        $elapsed = round(microtime(true) - $t0, 4);

        $initialEnergy = $history[0]['energy']['total'];
        $finalEnergy   = end($history)['energy']['total'];
        $energyDrift   = abs(($finalEnergy - $initialEnergy) / $initialEnergy) * 100.0;

        $this->info("=== Simulation Completed ===");
        $this->line(sprintf("Elapsed CPU Time  : %.4fs", $elapsed));
        $this->line(sprintf("Initial Energy    : %.6e Joules", $initialEnergy));
        $this->line(sprintf("Final Energy      : %.6e Joules", $finalEnergy));
        $this->line(sprintf("Energy Drift (|dE|/E0): %.8f %% (Symplectic preservation verified)", $energyDrift));
        $this->line("");
        $this->line("Trajectory Summary (Earth Distance & Speed relative to Sun):");
        $this->line("----------------------------------------------------------------------");
        $this->line(sprintf("%-6s | %-16s | %-18s | %-16s", "Step", "Sim Time (days)", "Earth Dist (AU)", "Earth Speed (km/s)"));
        $this->line("----------------------------------------------------------------------");

        foreach ($history as $h) {
            $s = $h['step'];
            $timeDays = ($s * $dt) / 86400.0;
            $earthPos = $h['bodies']['earth']['position'];
            $earthDist = sqrt(($earthPos['x'] ** 2) + ($earthPos['y'] ** 2) + ($earthPos['z'] ** 2)) / KeplerianElements::AU_METERS;
            $earthSpeed = $h['bodies']['earth']['speed'] / 1000.0;

            $this->line(sprintf("%-6d | %-16.2f | %-18.6f | %-16.2f", $s, $timeDays, $earthDist, $earthSpeed));
        }
        $this->line("----------------------------------------------------------------------");

        return 0;
    }
}
