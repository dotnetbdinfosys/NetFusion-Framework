<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Database/Seeders/Seeder.php
| Module    : Core\Database\Seeders
| Namespace : NetFusion\Core\Database\Seeders
| Purpose   : Abstract base database seeder providing data fixture population capabilities.
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

namespace NetFusion\Core\Database\Seeders;

use NetFusion\Core\Database\Connection;

/**
 * NetFusion Base Database Seeder
 *
 * Provides a standardized structure for seeding initial records, test fixtures,
 * or master data into the database.
 *
 * @package NetFusion\Core\Database\Seeders
 * @version 1.0.0
 */
abstract class Seeder
{
    /**
     * Run the database seed records.
     *
     * @param Connection $db
     */
    abstract public function run(Connection $db): void;

    /**
     * Call an additional child seeder class.
     *
     * @param class-string<Seeder> $class
     */
    public function call(string $class): void
    {
        /** @var Connection $db */
        $db = app(\NetFusion\Core\Database\DatabaseManager::class)->connection();

        /** @var Seeder $seeder */
        $seeder = app($class);
        $seeder->run($db);
    }
}
