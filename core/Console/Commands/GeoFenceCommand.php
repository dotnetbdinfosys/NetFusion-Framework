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
use NetFusion\Core\Spatial\GeoFence\GeoFence;
use NetFusion\Core\Spatial\Geometry\Point;
use NetFusion\Core\Spatial\GeoSpatialManager;

/**
 * CLI command to manage and test Geo-Fences.
 * Usage: php netfusion geo:fence [--create=downtown] [--name="Downtown"] [--circle="23.8103,90.4125,1000"] [--test="23.8103,90.4125"]
 */
class GeoFenceCommand extends Command
{
    protected string $name = 'geo:fence';
    protected string $description = 'List, create, or test Point inclusion inside spatial Geo-Fences';

    public function handle(): int
    {
        /** @var GeoSpatialManager $manager */
        $manager = function_exists('app') && app()->has('geo') ? app('geo') : new GeoSpatialManager();

        $createId = $this->option('create');
        $name = $this->option('name') ?? 'Zone';
        $circleStr = $this->option('circle');
        $testCoord = $this->option('test');

        if ($createId !== null && $circleStr !== null) {
            $parts = explode(',', $circleStr);
            if (count($parts) >= 3) {
                $lat = (float) trim($parts[0]);
                $lng = (float) trim($parts[1]);
                $radius = (float) trim($parts[2]);
                $fence = $manager->createFence($createId, $name, new Point($lat, $lng), $radius);
                $this->info("Circular GeoFence [{$fence->getId()}] '{$fence->getName()}' created (Radius: {$radius}m).");
                return 0;
            }
        }

        if ($testCoord !== null) {
            $parts = explode(',', $testCoord);
            if (count($parts) >= 2) {
                $p = new Point((float) trim($parts[0]), (float) trim($parts[1]));
                $this->info("Testing point: Lat {$p->getLat()}, Lng {$p->getLng()}");

                $fences = $manager->monitor()->getFences();
                if (empty($fences)) {
                    $this->line("No registered geo-fences to test against.");
                    return 0;
                }

                foreach ($fences as $fence) {
                    $inside = $fence->containsPoint($p);
                    $status = $inside ? "\033[32mINSIDE\033[0m" : "\033[33mOUTSIDE\033[0m";
                    $this->line("- [{$fence->getId()}] {$fence->getName()}: {$status}");
                }
                return 0;
            }
        }

        $fences = $manager->monitor()->getFences();
        $this->info("=================================================");
        $this->info("         NetFusion Active Geo-Fences             ");
        $this->info("=================================================");
        $this->line("Total Registered Fences: " . count($fences));

        if (empty($fences)) {
            $this->line("No active geo-fences registered.");
        } else {
            foreach ($fences as $fence) {
                $type = $fence->isCircular() ? "Circular ({$fence->getRadiusMeters()}m)" : "Polygonal";
                $this->line("- [{$fence->getId()}] {$fence->getName()} ({$type})");
            }
        }
        $this->info("=================================================");

        return 0;
    }
}
