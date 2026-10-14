<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/SpatialSceneCommand.php
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
use NetFusion\Core\Spatial\SpatialManager;

class SpatialSceneCommand extends Command
{
    protected string $signature = 'spatial:scene {sceneId? : Spatial scene identifier}';
    protected string $description = 'Inspect hierarchical 3D scene graph, spatial nodes and transforms';

    public function handle(): int
    {
        $sceneId = (string) ($this->argument(0) ?: 'default_scene');

        /** @var SpatialManager $manager */
        $manager = $this->app->has('spatial')
            ? $this->app->make('spatial')
            : new SpatialManager();

        $scene = $manager->getScene($sceneId);
        if ($scene === null) {
            $scene = $manager->createScene($sceneId, 'Default Factory Digital Twin');
        }

        $this->info("=== Spatial Scene Graph: {$scene->getName()} ===");
        $this->line("Scene Identifier : " . $scene->getId());
        $this->line("Total Nodes      : " . $scene->count());
        $this->line(json_encode($scene->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return 0;
    }
}
