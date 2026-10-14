<?php
/**
 * NetFusion Framework - Enterprise 6G Wireless RIS CLI
 *
 * Command to simulate and evaluate cascaded RIS wireless channel performance.
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
use NetFusion\Core\Wireless\Ris\Enums\BeamformingAlgorithm;
use NetFusion\Core\Wireless\Ris\Enums\ChannelState;
use NetFusion\Core\Wireless\Ris\Enums\FrequencyBand;
use NetFusion\Core\Wireless\Ris\Enums\PhaseResolution;
use NetFusion\Core\Wireless\Ris\RisManager;

class RisChannelCommand extends Command
{
    protected string $signature = 'ris:channel
        {surface=ris_thz_channel : Metasurface ID}
        {--band=terahertz : Frequency band}
        {--res=continuous : Phase resolution}
        {--rows=8 : Grid rows}
        {--cols=8 : Grid columns}
        {--tx-dist=15.0 : Tx to RIS distance in meters}
        {--rx-dist=10.0 : RIS to Rx distance in meters}
        {--direct-dist=20.0 : Tx to Rx direct distance in meters}
        {--algo=mrt : Algorithm (mrt, zero_forcing)}';

    protected string $description = 'Simulate 6G cascaded RIS channel capacity, SNR gain and molecular absorption';

    public function handle(): int
    {
        $manager = ris();
        $surfaceId = (string) $this->argument('surface');

        $bandStr = (string) $this->option('band');
        $resStr = (string) $this->option('res');
        $rows = (int) $this->option('rows');
        $cols = (int) $this->option('cols');

        $band = FrequencyBand::tryFrom($bandStr) ?? FrequencyBand::Terahertz;
        $res = PhaseResolution::tryFrom($resStr) ?? PhaseResolution::Continuous;
        $algo = BeamformingAlgorithm::tryFrom((string) $this->option('algo')) ?? BeamformingAlgorithm::MaximumRatio;

        if (!$manager->hasArray($surfaceId)) {
            $manager->createArray($surfaceId, $rows, $cols, $band, $res);
        }

        $txDist = (float) $this->option('tx-dist');
        $rxDist = (float) $this->option('rx-dist');
        $directDist = (float) $this->option('direct-dist');

        $result = $manager->simulateChannelLink(
            $surfaceId,
            $txDist,
            $rxDist,
            $directDist,
            ChannelState::Obstructed,
            $algo
        );

        $this->info("=== 6G RIS Cascaded Channel Simulation: [{$surfaceId}] ===");
        $this->line("Carrier Freq       : {$result['carrier_freq_ghz']} GHz");
        $this->line("Elements           : {$result['total_elements']} ({$rows}x{$cols})");
        $this->line("Algorithm          : {$result['algorithm']}");
        $this->line("Direct Capacity    : {$result['direct_capacity_bps_hz']} bps/Hz");
        $this->line("Effective Capacity : {$result['effective_capacity_bps_hz']} bps/Hz");
        $this->line("RIS SNR Gain       : +{$result['snr_gain_db']} dB");
        $this->line("Quantization Error : {$result['quantization_error_rad']} rad");

        return 0;
    }
}

