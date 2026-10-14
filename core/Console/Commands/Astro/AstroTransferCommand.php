<?php
/**
 * NetFusion Framework - Enterprise Astro-Informatics CLI
 *
 * Command to calculate interplanetary Hohmann transfer orbit delta-v and flight times.
 *
 * @package    NetFusion\Core\Console\Commands\Astro
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Astro;

use NetFusion\Core\Astro\Orbit\KeplerianElements;
use NetFusion\Core\Console\Command;

class AstroTransferCommand extends Command
{
    protected string $name = 'astro:transfer';

    protected string $signature = 'astro:transfer {--from=1.0 : Departure circular orbit radius in AU} {--to=1.524 : Arrival circular orbit radius in AU (Mars)}';

    protected string $description = 'Compute Hohmann interplanetary orbital transfer delta-v and time of flight';

    public function handle(): int
    {
        $manager = astro();

        $fromAu = (float) ($this->option('from') ?: 1.0);
        $toAu   = (float) ($this->option('to') ?: 1.524);

        $r1 = $fromAu * KeplerianElements::AU_METERS;
        $r2 = $toAu   * KeplerianElements::AU_METERS;

        $res = $manager->calculateHohmann($r1, $r2, KeplerianElements::MU_SUN);

        $dv1Km = $res['delta_v1'] / 1000.0;
        $dv2Km = $res['delta_v2'] / 1000.0;
        $totalDvKm = $res['total_delta_v'] / 1000.0;
        $flightDays = $res['transfer_time_seconds'] / 86400.0;
        $transferSemiMajorAu = $res['transfer_semi_major_axis'] / KeplerianElements::AU_METERS;

        $this->info("=== Hohmann Interplanetary Transfer Maneuver ===");
        $this->line(sprintf("Departure Orbit (r1): %.4f AU (%.3e m)", $fromAu, $r1));
        $this->line(sprintf("Target Orbit    (r2): %.4f AU (%.3e m)", $toAu, $r2));
        $this->line(sprintf("Transfer Ellipse (a): %.4f AU (%.3e m)", $transferSemiMajorAu, $res['transfer_semi_major_axis']));
        $this->line("----------------------------------------------------------");
        $this->line(sprintf("Delta-v 1 (Departure Burn): %8.3f km/s (%.1f m/s)", $dv1Km, $res['delta_v1']));
        $this->line(sprintf("Delta-v 2 (Arrival Burn)  : %8.3f km/s (%.1f m/s)", $dv2Km, $res['delta_v2']));
        $this->info(sprintf("Total Delta-v Required    : %8.3f km/s", $totalDvKm));
        $this->line(sprintf("Time of Flight (TOF)      : %8.2f days (%.2f months)", $flightDays, $flightDays / 30.4375));
        $this->line("----------------------------------------------------------");

        return 0;
    }
}
