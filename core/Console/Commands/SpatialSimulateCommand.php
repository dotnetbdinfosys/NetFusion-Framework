<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/SpatialSimulateCommand.php
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
use NetFusion\Core\Spatial\Enums\PhysicsBodyType;
use NetFusion\Core\Spatial\Math\Vector3;
use NetFusion\Core\Spatial\Physics\RigidBody;
use NetFusion\Core\Spatial\SpatialManager;

class SpatialSimulateCommand extends Command
{
    protected string $signature = 'spatial:simulate {sceneId? : Scene identifier} {--steps=30 : Number of physics steps to simulate} {--dt=0.016 : Delta time per step}';
    protected string $description = 'Simulate 3D Newtonian physics, gravity and rigid body dynamics';

    public function handle(): int
    {
        $sceneId = (string) ($this->argument(0) ?: 'physics_test_scene');
        $steps = (int) ($this->option('steps') ?: 30);
        $dt = (float) ($this->option('dt') ?: 0.016);

        /** @var SpatialManager $manager */
        $manager = $this->app->has('spatial')
            ? $this->app->make('spatial')
            : new SpatialManager();

        $manager->createScene($sceneId);
        $world = $manager->getPhysicsWorld($sceneId);

        $body = new RigidBody(
            id: 'drop_sphere',
            position: new Vector3(0.0, 10.0, 0.0),
            type: PhysicsBodyType::DYNAMIC,
            mass: 2.0,
            restitution: 0.7
        );
        $world->addBody($body);
        $world->setGroundHeight(0.0);

        $this->info("=== Spatial 3D Physics Simulation ===");
        $this->line("Scene: {$sceneId} | Steps: {$steps} | Initial Height: {$body->position->y}m");

        for ($i = 1; $i <= $steps; $i++) {
            $world->step($dt);
            if ($i % 5 === 0 || $i === $steps) {
                $this->line(sprintf("Step %02d (t=%.3fs): Pos=(%.2f, %.2f, %.2f) VelY=%.2f m/s", $i, $i * $dt, $body->position->x, $body->position->y, $body->position->z, $body->velocity->y));
            }
        }

        $this->info("Simulation completed successfully.");
        return 0;
    }
}
