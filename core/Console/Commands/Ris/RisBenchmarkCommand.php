<?php
/**
 * NetFusion Framework - Enterprise 6G Wireless RIS CLI
 *
 * Command to benchmark continuous versus discrete 1-bit / 2-bit quantization gain.
 *
 * @package    NetFusion\Console\Commands\Ris
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Ris;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Wireless\Ris\Enums\FrequencyBand;
use NetFusion\Core\Wireless\Ris\Enums\PhaseResolution;
use NetFusion\Core\Wireless\Ris\RisManager;

class RisBenchmarkCommand extends Command
{
    protected string $signature = 'ris:benchmark
        {--rows=8 : Grid rows}
        {--cols=8 : Grid columns}
        {--band=terahertz : Frequency band}';

    protected string $description = 'Benchmark continuous vs 1-bit, 2-bit, and 3-bit RIS quantization performance';

    public function handle(): int
    {
        $manager = ris();
        $rows = (int) $this->option('rows');

        $cols = (int) $this->option('cols');
        $bandStr = (string) $this->option('band');
        $band = FrequencyBand::tryFrom($bandStr) ?? FrequencyBand::Terahertz;

        $resolutions = [
            'Continuous' => PhaseResolution::Continuous,
            '3-Bit'      => PhaseResolution::ThreeBit,
            '2-Bit'      => PhaseResolution::TwoBit,
            '1-Bit'      => PhaseResolution::OneBit,
        ];

        $this->info("=== 6G RIS Quantization Benchmark ({$rows}x{$cols} elements, {$band->value}) ===");

        foreach ($resolutions as $label => $res) {
            $surfaceId = "bench_{$res->value}";
            $manager->createArray($surfaceId, $rows, $cols, $band, $res);

            $sim = $manager->simulateChannelLink($surfaceId, 15.0, 10.0, 20.0);

            $this->line(sprintf(
                "%-12s | SNR Gain: %6.2f dB | Capacity: %6.2f bps/Hz | Phase Error: %6.4f rad",
                $label,
                $sim['snr_gain_db'],
                $sim['effective_capacity_bps_hz'],
                $sim['quantization_error_rad']
            ));
        }

        return 0;
    }
}

