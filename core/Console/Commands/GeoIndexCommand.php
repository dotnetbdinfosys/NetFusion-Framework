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
use NetFusion\Core\Spatial\Geometry\BoundingBox;
use NetFusion\Core\Spatial\Geometry\Point;
use NetFusion\Core\Spatial\Index\RTree;

/**
 * CLI command to inspect and benchmark R-Tree spatial indexing.
 * Usage: php netfusion geo:index [--knn="23.8103,90.4125"] [--k=3]
 */
class GeoIndexCommand extends Command
{
    protected string $name = 'geo:index';
    protected string $description = 'Demonstrate R-Tree 2D spatial indexing and K-Nearest Neighbors (KNN) search';

    public function handle(): int
    {
        $knnCoord = $this->option('knn') ?? '23.8103,90.4125';
        $k = (int) ($this->option('k') ?? 3);

        $parts = explode(',', $knnCoord);
        $target = new Point((float) trim($parts[0]), (float) trim($parts[1] ?? '0.0'));

        $tree = new RTree(maxEntries: 8);

        // Populate sample points of interest (POIs) in Dhaka city
        $pois = [
            ['id' => 'poi-motijheel', 'name' => 'Motijheel Commercial Area', 'lat' => 23.7330, 'lng' => 90.4172],
            ['id' => 'poi-gulshan', 'name' => 'Gulshan Circle 1', 'lat' => 23.7806, 'lng' => 90.4167],
            ['id' => 'poi-banani', 'name' => 'Banani Road 11', 'lat' => 23.7937, 'lng' => 90.4043],
            ['id' => 'poi-dhanmondi', 'name' => 'Dhanmondi Lake', 'lat' => 23.7461, 'lng' => 90.3742],
            ['id' => 'poi-uttara', 'name' => 'Uttara Sector 3', 'lat' => 23.8681, 'lng' => 90.3995],
        ];

        foreach ($pois as $poi) {
            $p = new Point($poi['lat'], $poi['lng']);
            $tree->insertPoint($p, $poi, $poi['id']);
        }

        $nearest = $tree->knn($target, $k);

        $this->info("=================================================");
        $this->info("      R-Tree K-Nearest Neighbors (KNN) Search    ");
        $this->info("=================================================");
        $this->line("Target Location : Lat {$target->getLat()}, Lng {$target->getLng()}");
        $this->line("Indexed Points  : " . $tree->count() . " POIs");
        $this->line("K Neighbors     : {$k}");
        $this->line("-------------------------------------------------");

        foreach ($nearest as $rank => $match) {
            $poi = $match['data'];
            $this->line(sprintf(
                "#%d: [%s] %s | Dist: %s meters",
                $rank + 1,
                $poi['id'],
                $poi['name'],
                number_format($match['distanceMeters'], 1)
            ));
        }
        $this->info("=================================================");

        return 0;
    }
}
