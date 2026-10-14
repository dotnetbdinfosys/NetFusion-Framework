<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Backup/Dumpers/MysqlDumper.php
| Module    : Core\Backup
| Namespace : NetFusion\Core\Backup\Dumpers
| Purpose   : Pure PHP MySQL / MariaDB schema and data dumper engine.
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

namespace NetFusion\Core\Backup\Dumpers;

use NetFusion\Core\Backup\Contracts\BackupDumperInterface;
use NetFusion\Core\Database\Connection;
use PDO;
use Throwable;

class MysqlDumper implements BackupDumperInterface
{
    /**
     * Dump MySQL database tables into an SQL text file.
     */
    public function dump(Connection $connection, string $destinationPath): bool
    {
        try {
            $pdo = $connection->getPdo();
            $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);

            $sql = "-- NetFusion Framework MySQL Dump\n";
            $sql .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
            $sql .= "SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n\n";

            foreach ($tables as $tableRow) {
                $tableName = $tableRow[0];

                $createTableStmt = $pdo->query("SHOW CREATE TABLE `{$tableName}`")->fetch(PDO::FETCH_NUM);
                $createSql = $createTableStmt[1] ?? '';

                $sql .= "-- Table structure for `{$tableName}`\n";
                $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sql .= "{$createSql};\n\n";

                // Table rows
                $rows = $pdo->query("SELECT * FROM `{$tableName}`")->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    $sql .= "-- Dumping data for `{$tableName}`\n";
                    foreach ($rows as $row) {
                        $columns = array_keys($row);
                        $escapedColumns = array_map(fn($col) => "`{$col}`", $columns);
                        $escapedValues = array_map(function ($val) use ($pdo) {
                            if ($val === null) {
                                return 'NULL';
                            }
                            return $pdo->quote((string) $val);
                        }, array_values($row));

                        $sql .= "INSERT INTO `{$tableName}` (" . implode(', ', $escapedColumns) . ") VALUES (" . implode(', ', $escapedValues) . ");\n";
                    }
                    $sql .= "\n";
                }
            }

            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

            $dir = dirname($destinationPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            return file_put_contents($destinationPath, $sql) !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Restore database from an SQL file.
     */
    public function restore(Connection $connection, string $sourcePath): bool
    {
        if (!file_exists($sourcePath)) {
            return false;
        }

        try {
            $pdo = $connection->getPdo();
            $sql = (string) file_get_contents($sourcePath);
            $pdo->exec($sql);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
