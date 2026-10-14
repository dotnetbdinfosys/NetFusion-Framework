<?php
/**
 * NetFusion Framework - Enterprise Plasma Physics CLI
 *
 * Command to analyze Tokamak magnetic confinement geometry, q-profile, and Kruskal-Shafranov stability.
 *
 * @package    NetFusion\Core\Console\Commands\Plasma
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Plasma;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Physics\Plasma\Tokamak\ToroidalField;

class PlasmaTokamakCommand extends Command
{
    protected string $name = 'plasma:tokamak';

    protected string $signature = 'plasma:tokamak {--r0=6.2 : Major radius in m} {--a=2.0 : Minor radius in m} {--b0=5.3 : Toroidal B0 in Tesla} {--ip=15.0 : Plasma current in MA}';

    protected string $description = 'Analyze Tokamak magnetic confinement field, safety factor q-profile, and MHD stability';

    public function handle(): int
    {
        $manager = plasma();

        $r0   = (float) ($this->option('r0') ?: 6.2);
        $a    = (float) ($this->option('a') ?: 2.0);
        $b0   = (float) ($this->option('b0') ?: 5.3);
        $ipMA = (float) ($this->option('ip') ?: 15.0);

        $tokamak = new ToroidalField($r0, $a, $b0, $ipMA * 1e6);
        $manager->setTokamak($tokamak);

        $stability = $manager->verifyMhdStability(0.025);

        $this->info("=== Tokamak Toroidal Magnetic Confinement Analysis ===");
        $this->line(sprintf("Major Radius (R_0)   : %.2f m", $tokamak->getMajorRadius()));
        $this->line(sprintf("Minor Radius (a)     : %.2f m", $tokamak->getMinorRadius()));
        $this->line(sprintf("Aspect Ratio (A)     : %.2f (Inverse epsilon: %.3f)", $tokamak->getAspectRatio(), $tokamak->getInverseAspectRatio()));
        $this->line(sprintf("Toroidal Field (B_0) : %.2f Tesla", $tokamak->getToroidalB0()));
        $this->line(sprintf("Plasma Current (I_p) : %.2f MA", $ipMA));
        $this->line(sprintf("Plasma Volume (V)    : %.1f m^3", $tokamak->getPlasmaVolume()));
        $this->line(sprintf("Cross-Section Area   : %.2f m^2", $tokamak->getCrossSectionArea()));
        $this->line("----------------------------------------------------------");
        $this->info("=== Safety Factor (q) Profile & Stability Margins ===");
        $this->line(sprintf("Core Safety Factor (q_0) : %.2f", $tokamak->getSafetyFactorAt(0.0)));
        $this->line(sprintf("Mid-Radius q(r=0.5 a)    : %.2f", $tokamak->getSafetyFactorAt(0.5)));
        $this->line(sprintf("Edge Safety Factor (q_a) : %.2f", $tokamak->getEdgeSafetyFactor()));
        $this->line(sprintf("Edge Poloidal Field (B_p): %.3f Tesla", $tokamak->getEdgePoloidalField()));
        $this->line(sprintf("Kruskal-Shafranov Limit  : %s (q_edge > 1.0)", $stability['ks_stable'] ? 'PASS (STABLE)' : 'FAIL (KINK DISRUPTION)'));
        $this->line(sprintf("Troyon Beta Operational  : Limit = %.2f %% (Beta_t = %.2f %%)", $stability['troyon_limit'] * 100.0, $stability['beta_t'] * 100.0));
        $this->line("----------------------------------------------------------");

        return 0;
    }
}
