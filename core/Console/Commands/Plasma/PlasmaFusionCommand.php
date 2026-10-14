<?php
/**
 * NetFusion Framework - Enterprise Plasma Physics CLI
 *
 * Command to compute Maxwellian thermonuclear reactivity and volumetric fusion power density.
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

class PlasmaFusionCommand extends Command
{
    protected string $name = 'plasma:fusion';

    protected string $signature = 'plasma:fusion {temp? : Ion temperature in keV} {--fuel=d_t : Fuel mixture (d_t, d_d_n, d_he3, p_b11)}';

    protected string $description = 'Compute thermonuclear fusion reaction rate, reactivity, and power density';

    public function handle(): int
    {
        $manager = plasma();

        $tKeV = (float) ($this->argument(0) ?: 15.0);
        $fuelStr = (string) ($this->option('fuel') ?: 'd_t');

        $fuel = FuelMixture::tryFrom($fuelStr) ?? FuelMixture::D_T;

        $res = $manager->calculateReactionRate($tKeV, $fuel);

        $sv = $res['reactivity'];
        $pFusKw = $res['total_power_density_w'] / 1e3;
        $pChargedKw = $res['charged_power_density_w'] / 1e3;
        $pBremsKw = $res['bremsstrahlung_loss_w'] / 1e3;

        $this->info("=== Thermonuclear Fusion Kinetics Analysis ===");
        $this->line(sprintf("Fuel Mixture      : %s (Reaction Energy: %.2f MeV)", $fuel->value, $fuel->reactionEnergyMeV()));
        $this->line(sprintf("Aneutronic Status : %s", $fuel->isAneutronic() ? 'YES (No fast neutron radiation)' : 'NO (DT/DD neutron emission)'));
        $this->line(sprintf("Ion Temperature   : %.2f keV (~%.1f Million Kelvin)", $tKeV, $tKeV * 11.604));
        $this->line(sprintf("Optimal Host Temp : %.2f keV", $fuel->optimalTemperatureKeV()));
        $this->line("----------------------------------------------------------");
        $this->line(sprintf("Maxwellian Reactivity <sigma*v>: %.4e m^3/s", $sv));
        $this->line(sprintf("Total Fusion Power Density      : %.2f kW/m^3", $pFusKw));
        $this->line(sprintf("Charged Particle Heating Density: %.2f kW/m^3 (%.1f%%)", $pChargedKw, $fuel->chargedFraction() * 100.0));
        $this->line(sprintf("Bremsstrahlung Radiation Loss   : %.2f kW/m^3", $pBremsKw));
        $this->line(sprintf("Net Volumetric Power Margin     : %.2f kW/m^3", $pChargedKw - $pBremsKw));
        $this->line("----------------------------------------------------------");

        return 0;
    }
}
