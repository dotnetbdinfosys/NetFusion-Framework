<?php
/**
 * NetFusion Framework - Enterprise Computational Fluid Dynamics CLI
 *
 * Command to simulate fluid channel flow around a solid obstacle (e.g. cylinder).
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

class LbmFlowCommand extends Command
{
    protected string $name = 'lbm:flow';

    protected string $signature = 'lbm:flow {--width=50 : Channel width Nx} {--height=25 : Channel height Ny} {--steps=35 : Timesteps} {--u=0.06 : Inflow speed}';

    protected string $description = 'Simulate 2D hydrodynamic flow past a circular cylinder obstacle';

    public function handle(): int
    {
        $manager = lbm();

        $width  = (int) ($this->option('width') ?: 50);
        $height = (int) ($this->option('height') ?: 25);
        $steps  = (int) ($this->option('steps') ?: 35);
        $u      = (float) ($this->option('u') ?: 0.06);

        $manager->setTau(0.65);
        $grid = $manager->createGrid($width, $height);
        $grid->setupChannel($u);

        // Place cylindrical obstacle at 1/4th length
        $cx = (int) round($width / 4);
        $cy = (int) round($height / 2);
        $radius = max(2.0, round($height / 8));
        $grid->addCylinder($cx, $cy, $radius);

        $this->info("Simulating Channel Flow Past Cylindrical Obstacle...");
        $this->line(sprintf("Domain: %dx%d | Cylinder at (%d, %d) with radius %.1f", $width, $height, $cx, $cy, $radius));
        $this->line(sprintf("Inflow Speed: %.4f | Viscosity nu: %.4f", $u, $manager->getViscosity()));

        $t0 = microtime(true);
        $manager->step($steps);
        $elapsed = round(microtime(true) - $t0, 4);

        $field = $manager->extractField();
        $re = $field->getReynoldsNumber($radius * 2.0, $manager->getViscosity());

        $this->info("=== Flow Evolution Finished ===");
        $this->line(sprintf("Time Steps: %d (in %.4fs) | Characteristic Re: %.2f", $steps, $elapsed, $re));
        $this->line(sprintf("Max Speed : %.4f | Vorticity Curl Extracted: [OK]", $field->getMaxSpeed()));
        $this->line("");
        $this->line("Velocity Field (Solid Obstacle shown as '█'):");
        $this->line("--------------------------------------------------");
        echo $field->renderAscii(min(50, $width), min(14, $height));
        $this->line("--------------------------------------------------");

        return 0;
    }
}
