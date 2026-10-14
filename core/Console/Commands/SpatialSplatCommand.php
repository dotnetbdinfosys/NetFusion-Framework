<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/SpatialSplatCommand.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Spatial\Gaussian\GaussianPrimitive;
use NetFusion\Core\Spatial\Math\Quaternion;
use NetFusion\Core\Spatial\Math\Vector3;
use NetFusion\Core\Spatial\SpatialManager;

class SpatialSplatCommand extends Command
{
    protected string $signature = 'spatial:splat {cloudId? : Gaussian splat cloud identifier} {--count=100 : Number of test splats to synthesize}';
    protected string $description = 'Inspect 3D Gaussian Splatting point cloud, radiance bounds and depth ordering';

    public function handle(): int
    {
        $cloudId = (string) ($this->argument(0) ?: 'splat_cloud_01');
        $count = (int) ($this->option('count') ?: 100);

        /** @var SpatialManager $manager */
        $manager = $this->app->has('spatial')
            ? $this->app->make('spatial')
            : new SpatialManager();

        $cloud = $manager->getSplatCloud($cloudId);
        if ($cloud === null) {
            $cloud = $manager->createSplatCloud($cloudId, 'Synthetic 3DGS Cloud');
            for ($i = 0; $i < $count; $i++) {
                $cloud->addSplat(new GaussianPrimitive(
                    position: new Vector3(mt_rand(-50, 50) / 10, mt_rand(0, 50) / 10, mt_rand(-50, 50) / 10),
                    scale: new Vector3(0.1, 0.1, 0.1),
                    rotation: Quaternion::identity(),
                    opacity: 0.85,
                    colorRgb: [0.8, 0.2, 0.4]
                ));
            }
        }

        $bounds = $cloud->computeBounds();
        $this->info("=== 3D Gaussian Splatting Cloud: {$cloud->getName()} ===");
        $this->line("Cloud Identifier : " . $cloud->getId());
        $this->line("Total Splats     : " . $cloud->count());
        $this->line("Center Point     : (" . round($bounds['center']->x, 2) . ", " . round($bounds['center']->y, 2) . ", " . round($bounds['center']->z, 2) . ")");
        $this->line("AABB Min Bound   : (" . round($bounds['min']->x, 2) . ", " . round($bounds['min']->y, 2) . ", " . round($bounds['min']->z, 2) . ")");
        $this->line("AABB Max Bound   : (" . round($bounds['max']->x, 2) . ", " . round($bounds['max']->y, 2) . ", " . round($bounds['max']->z, 2) . ")");
        return 0;
    }
}
