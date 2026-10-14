<?php
/**
 * NetFusion Framework - Enterprise Plasma Physics CLI
 *
 * Command to evaluate Lawson criterion, triple product, energy gain Q-factor, and ignition conditions.
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
use NetFusion\Core\Physics\Plasma\Enums\FuelMixture;

class PlasmaLawsonCommand extends Command
{
    protected string $name = 'plasma:lawson';

    protected string $signature = 'plasma:lawson {--density=1.0 : Plasma density in 10^20 m^-3} {--temp=15.0 : Temperature in keV} {--tau=3.5 : Confinement time tau_E in sec} {--aux=50.0 : Auxiliary heating in MW} {--fuel=d_t : Fuel mixture}';

    protected string $description = 'Evaluate Lawson triple product, thermonuclear Q-gain factor, and ignition margins';

    public function handle(): int
    {
        $manager = plasma();

        $nE20   = (float) ($this->option('density') ?: 1.0);
        $tKeV   = (float) ($this->option('temp') ?: 15.0);
        $tauSec = (float) ($this->option('tau') ?: 3.5);
        $auxMw  = (float) ($this->option('aux') ?: 50.0);
        $fuelStr = (string) ($this->option('fuel') ?: 'd_t');

        $fuel = FuelMixture::tryFrom($fuelStr) ?? FuelMixture::D_T;

        $res = $manager->evaluateLawson($nE20 * 1e20, $tKeV, $tauSec, $auxMw, $fuel);

        $this->info("=== Lawson Criterion & Fusion Reactor Power Balance ===");
        $this->line(sprintf("Fuel Mixture             : %s", $fuel->value));
        $this->line(sprintf("Plasma Density (n_e)     : %.2f x 10^20 m^-3", $nE20));
        $this->line(sprintf("Temperature (T_i)        : %.2f keV", $tKeV));
        $this->line(sprintf("Confinement Time (tau_E) : %.2f seconds", $tauSec));
        $this->line(sprintf("Auxiliary Heating (P_aux): %.2f MW", $auxMw));
        $this->line("----------------------------------------------------------");
        $this->line(sprintf("Lawson Triple Product    : %.2e m^-3 * keV * s", $res['triple_product']));
        $this->line(sprintf("Ignition Threshold (D-T) : 3.00e+21 m^-3 * keV * s"));
        $this->line(sprintf("Total Fusion Power (P_fus): %8.2f MW", $res['fusion_power_mw']));
        $this->line(sprintf("Alpha Self-Heating (P_a)  : %8.2f MW", $res['alpha_heating_mw']));
        $this->line(sprintf("Thermal & Rad Loss (P_los): %8.2f MW", $res['loss_power_mw']));
        $this->info(sprintf("Energy Gain Factor (Q)    : Q = %.2f", $res['q_factor']));
        $this->line("----------------------------------------------------------");
        $this->line(sprintf("Scientific Breakeven (Q>=1): %s", $res['is_breakeven'] ? 'ACHIEVED [Q >= 1.0]' : 'NOT REACHED [Q < 1.0]'));
        $this->line(sprintf("Thermonuclear Ignition    : %s", $res['is_ignited'] ? 'ACHIEVED (Self-sustaining, P_alpha >= P_loss)' : 'EXTERNAL HEATING REQUIRED'));
        $this->line("----------------------------------------------------------");

        return 0;
    }
}
