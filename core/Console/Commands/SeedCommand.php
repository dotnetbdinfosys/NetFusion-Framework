<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/SeedCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command to execute database seeder classes across modules.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Database\Seeders\Seeder;

/**
 * NetFusion Database Seed Command
 *
 * Runs registered database seeders to populate tables with initial or fixture data.
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class SeedCommand extends Command
{
    protected string $name = 'db:seed';
    protected string $description = 'Seed the database with records (--class=[SeederClass] --module=[Name])';

    public function handle(): int
    {
        $class  = $this->option('class');
        $module = $this->option('module');

        if (!$class) {
            if ($module) {
                $class = "NetFusion\\Modules\\{$module}\\Database\\Seeders\\{$module}DatabaseSeeder";
            } else {
                $class = "NetFusion\\App\\Database\\Seeders\\DatabaseSeeder";
            }
        }

        if (!class_exists($class)) {
            $this->error("Seeder class [{$class}] does not exist.");
            return 1;
        }

        $this->info("Seeding: {$class}...");

        /** @var Seeder $seeder */
        $seeder = $this->app->make($class);

        if (!($seeder instanceof Seeder)) {
            $this->error("Class [{$class}] must extend NetFusion\\Core\\Database\\Seeders\\Seeder.");
            return 1;
        }

        /** @var DatabaseManager $dbManager */
        $dbManager  = $this->app->make(DatabaseManager::class);
        $connection = $dbManager->connection($this->option('connection'));

        $seeder->run($connection);

        $this->success("Database seeding completed successfully!");
        return 0;
    }
}
