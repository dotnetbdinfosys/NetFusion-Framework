<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Spatial\H3\H3Index;

/**
 * CLI command to encode, decode, and inspect Uber H3 hexagonal spatial grid cells.
 * Usage: php netfusion geo:h3 23.8103 90.4125 [--res=8] [--k=1]
 */
class GeoH3Command extends Command
{
    protected string $name = 'geo:h3';
    protected string $description = 'Encode geographic coordinates into H3 hexagon index and inspect k-ring neighbors';

    public function handle(): int
    {
        $latArg = $this->argument(0) ?? '23.8103';
        $lngArg = $this->argument(1) ?? '90.4125';

        $lat = (float) $latArg;
        $lng = (float) $lngArg;
        $res = (int) ($this->option('res') ?? 8);
        $k = (int) ($this->option('k') ?? 0);

        $h3 = H3Index::geoToH3($lat, $lng, $res);
        $centroid = H3Index::h3ToGeo($h3);

        $this->info("=================================================");
        $this->info("      Uber H3 Hexagonal Grid Cell Telemetry      ");
        $this->info("=================================================");
        $this->line("Input Lat/Lng  : {$lat}, {$lng}");
        $this->line("Resolution     : {$res} (Avg Edge: " . (H3Index::RES_EDGE_LENGTHS[$res] ?? 0) . "m)");
        $this->line("H3 Cell Index  : \033[32m{$h3}\033[0m");
        $this->line("Cell Centroid  : " . round($centroid->getLat(), 6) . ", " . round($centroid->getLng(), 6));

        if ($k > 0) {
            $neighbors = H3Index::kRing($h3, $k);
            $this->line("-------------------------------------------------");
            $this->info("k-Ring Neighbors (Distance <= {$k}): " . count($neighbors) . " cells");
            foreach (array_slice($neighbors, 0, 10) as $n) {
                $this->line("- {$n}" . ($n === $h3 ? " (Center)" : ""));
            }
            if (count($neighbors) > 10) {
                $this->line("... and " . (count($neighbors) - 10) . " more cells.");
            }
        }
        $this->info("=================================================");

        return 0;
    }
}
