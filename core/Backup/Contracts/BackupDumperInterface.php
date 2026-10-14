<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Backup/Contracts/BackupDumperInterface.php
| Module    : Core\Backup
| Namespace : NetFusion\Core\Backup\Contracts
| Purpose   : Contract interface for database dumpers in the disaster recovery engine.
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

namespace NetFusion\Core\Backup\Contracts;

use NetFusion\Core\Database\Connection;

interface BackupDumperInterface
{
    /**
     * Dump database tables into an SQL output file.
     *
     * @param  Connection $connection
     * @param  string     $destinationPath Absolute path to write the SQL dump.
     * @return bool
     */
    public function dump(Connection $connection, string $destinationPath): bool;

    /**
     * Restore database tables from an SQL input file.
     *
     * @param  Connection $connection
     * @param  string     $sourcePath Absolute path to read the SQL dump.
     * @return bool
     */
    public function restore(Connection $connection, string $sourcePath): bool;
}
