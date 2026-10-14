<?php
/**
 * NetFusion Framework - Enterprise 6G Wireless RIS CLI
 *
 * Command to steer a RIS metasurface toward target angular coordinates.
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

class RisSteerCommand extends Command
{
    protected string $signature = 'ris:steer
        {surface=ris_thz_01 : Metasurface ID}
        {--band=terahertz : Frequency band (sub_6ghz, mmwave, terahertz, extreme_thz)}
        {--res=continuous : Phase resolution (continuous, 1_bit, 2_bit, 3_bit)}
        {--rows=8 : Metasurface rows}
        {--cols=8 : Metasurface columns}
        {--in-azimuth=0.0 : Incident beam azimuth angle in degrees}
        {--in-elevation=30.0 : Incident beam elevation angle in degrees}
        {--out-azimuth=45.0 : Desired reflected beam azimuth angle in degrees}
        {--out-elevation=15.0 : Desired reflected beam elevation angle in degrees}';

    protected string $description = 'Steer a 6G RIS metasurface beam toward target spherical coordinates';

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

        if (!$manager->hasArray($surfaceId)) {
            $manager->createArray($surfaceId, $rows, $cols, $band, $res);
        }

        $inAz = (float) $this->option('in-azimuth');
        $inEl = (float) $this->option('in-elevation');
        $outAz = (float) $this->option('out-azimuth');
        $outEl = (float) $this->option('out-elevation');

        $result = $manager->steerBeam($surfaceId, $inAz, $inEl, $outAz, $outEl);

        $this->info("=== 6G RIS Beam Steering: [{$surfaceId}] ===");
        $this->line("Frequency Band : {$result['band']} (" . round($band->frequencyHz() / 1e9, 2) . " GHz)");
        $this->line("Array Geometry : {$rows}x{$cols} ({$result['elements']} metamaterial elements)");
        $this->line("Incident Beam  : Azimuth {$result['in_azimuth_deg']}°, Elevation {$result['in_elevation_deg']}°");
        $this->line("Reflected Beam : Azimuth {$result['out_azimuth_deg']}°, Elevation {$result['out_elevation_deg']}°");
        $this->line("Avg Phase Error: {$result['average_quantization_error_rad']} rad");

        return 0;
    }
}

