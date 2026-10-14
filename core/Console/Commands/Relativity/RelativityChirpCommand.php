<?php
/**
 * NetFusion Framework - Enterprise Numerical Relativity CLI
 *
 * Command to simulate binary black hole inspiral-merger-ringdown chirp waveform.
 *
 * @package    NetFusion\Core\Console\Commands\Relativity
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Relativity;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Relativity\RelativityManager;

class RelativityChirpCommand extends Command
{
    protected string $signature = 'relativity:chirp
        {--m1=36.0 : Primary component mass in solar masses}
        {--m2=29.0 : Secondary component mass in solar masses}
        {--dist=410.0 : Luminosity distance in Mpc}
        {--duration=0.2 : Time duration in seconds}
        {--rate=2048.0 : Sample rate in Hz}';

    protected string $description = 'Simulate binary black hole inspiral-merger-ringdown gravitational chirp waveform';

    public function handle(): int
    {
        $manager = relativity();
        $m1 = (float) $this->option('m1');

        $m2 = (float) $this->option('m2');
        $dist = (float) $this->option('dist');
        $duration = (float) $this->option('duration');
        $rate = (float) $this->option('rate');

        $result = $manager->simulateBinaryChirp($m1, $m2, $dist, $duration, $rate);

        $this->info("=== Gravitational Waveform Chirp Simulation (BBH {$m1} + {$m2} M☉) ===");
        $this->line("Chirp Mass (Mc)       : {$result['chirp_mass_solar']} M☉");
        $this->line("Total Initial Mass    : {$result['total_mass_solar']} M☉");
        $this->line("Remnant Final Mass    : {$result['remnant_mass_solar']} M☉");
        $this->line("ISCO Frequency        : {$result['isco_frequency_hz']} Hz");
        $this->line("QNM Ringdown Frequency: {$result['ringdown_frequency_hz']} Hz");
        $this->line("Peak Strain (h_peak)  : " . sprintf("%.3e", $result['peak_strain']));
        $this->line("Total Strain Samples  : {$result['num_samples']} samples");

        return 0;
    }
}
