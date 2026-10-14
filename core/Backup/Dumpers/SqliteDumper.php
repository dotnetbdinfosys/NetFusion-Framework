<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Backup/Dumpers/SqliteDumper.php
| Module    : Core\Backup
| Namespace : NetFusion\Core\Backup\Dumpers
| Purpose   : Pure PHP SQLite database dumper and restoration engine.
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

class SqliteDumper implements BackupDumperInterface
{
    /**
     * Dump SQLite schema and data into a standard SQL text file.
     */
    public function dump(Connection $connection, string $destinationPath): bool
    {
        try {
            $pdo = $connection->getPdo();
            $tables = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_ASSOC);

            $sql = "-- NetFusion Framework SQLite Dump\n";
            $sql .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "PRAGMA foreign_keys = OFF;\n\n";

            foreach ($tables as $table) {
                $tableName = $table['name'];
                $createSql = $table['sql'];

                $sql .= "-- Table: {$tableName}\n";
                $sql .= "DROP TABLE IF EXISTS \"{$tableName}\";\n";
                $sql .= "{$createSql};\n\n";

                // Export table data
                $rows = $pdo->query("SELECT * FROM \"{$tableName}\"")->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $columns = array_keys($row);
                        $escapedColumns = array_map(fn($col) => "\"{$col}\"", $columns);
                        $escapedValues = array_map(function ($val) use ($pdo) {
                            if ($val === null) {
                                return 'NULL';
                            }
                            return $pdo->quote((string) $val);
                        }, array_values($row));

                        $sql .= "INSERT INTO \"{$tableName}\" (" . implode(', ', $escapedColumns) . ") VALUES (" . implode(', ', $escapedValues) . ");\n";
                    }
                    $sql .= "\n";
                }
            }

            $sql .= "PRAGMA foreign_keys = ON;\n";

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
