<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Backup/BackupManager.php
| Module    : Core\Backup
| Namespace : NetFusion\Core\Backup
| Purpose   : Enterprise automated backup, disaster recovery, and snapshot manager.
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

namespace NetFusion\Core\Backup;

use DateTimeImmutable;
use NetFusion\Core\Backup\Contracts\BackupDumperInterface;
use NetFusion\Core\Backup\Dumpers\MysqlDumper;
use NetFusion\Core\Backup\Dumpers\SqliteDumper;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\Database\DatabaseManager;
use Throwable;
use ZipArchive;

class BackupManager
{
    protected string $backupPath;

    public function __construct(?string $backupPath = null)
    {
        $this->backupPath = $backupPath ?? (function_exists('storage_path') ? storage_path('backups') : 'storage/backups');
        if (!is_dir($this->backupPath)) {
            @mkdir($this->backupPath, 0775, true);
        }
    }

    /**
     * Get appropriate database dumper for connection.
     */
    public function getDumper(Connection $connection): BackupDumperInterface
    {
        $driver = $connection->getDriverName();
        return match ($driver) {
            'sqlite' => new SqliteDumper(),
            default  => new MysqlDumper(),
        };
    }

    /**
     * Execute a backup snapshot.
     *
     * @param  bool|array  $onlyDb
     * @param  string|null $customFilename
     * @return array<string, mixed>
     */
    public function run(bool|array $onlyDb = false, ?string $customFilename = null): array
    {
        if (is_array($onlyDb)) {
            $onlyDb = in_array('db', $onlyDb) && !in_array('files', $onlyDb);
        }

        $timestamp = (new DateTimeImmutable())->format('Y-m-d_His');
        $filename  = $customFilename ?? "backup_{$timestamp}.zip";
        if (!str_ends_with($filename, '.zip')) {
            $filename .= '.zip';
        }

        $zipPath  = $this->backupPath . DIRECTORY_SEPARATOR . $filename;
        $tempSql  = $this->backupPath . DIRECTORY_SEPARATOR . "dump_{$timestamp}.sql";

        /** @var DatabaseManager $dbManager */
        $dbManager  = app(DatabaseManager::class);
        $connection = $dbManager->connection();

        $dumper = $this->getDumper($connection);
        $dumpSuccess = $dumper->dump($connection, $tempSql);

        if (!$dumpSuccess || !file_exists($tempSql)) {
            throw new \RuntimeException("Database dump generation failed.");
        }

        $checksum = hash_file('sha256', $tempSql);

        // Package into ZIP if ZipArchive available
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $zip->addFile($tempSql, 'database.sql');

                // Add backup metadata manifest
                $manifest = json_encode([
                    'framework'    => 'NetFusion Framework',
                    'version'      => '1.0.0',
                    'author'       => 'Md. Atiquzzaman Ashish (DotNetBD)',
                    'created_at'   => (new DateTimeImmutable())->format('c'),
                    'sha256'       => $checksum,
                    'only_db'      => $onlyDb,
                    'db_driver'    => $connection->getDriverName(),
                ], JSON_PRETTY_PRINT);
                $zip->addFromString('manifest.json', $manifest);

                $zip->close();
                @unlink($tempSql);
            } else {
                // Fallback to raw sql if zip creation fails
                rename($tempSql, str_replace('.zip', '.sql', $zipPath));
                $zipPath = str_replace('.zip', '.sql', $zipPath);
                $filename = basename($zipPath);
            }
        } else {
            rename($tempSql, str_replace('.zip', '.sql', $zipPath));
            $zipPath = str_replace('.zip', '.sql', $zipPath);
            $filename = basename($zipPath);
        }

        $fileSize = file_exists($zipPath) ? filesize($zipPath) : 0;

        return [
            'success'   => true,
            'filename'  => $filename,
            'path'      => $zipPath,
            'size'      => $fileSize,
            'size_formatted' => $this->formatBytes($fileSize),
            'checksum'  => $checksum,
            'created_at'=> date('Y-m-d H:i:s'),
        ];
    }

    /**
     * List all available backup archives.
     *
     * @return array<array{filename: string, size: int, size_formatted: string, created_at: string, path: string}>
     */
    public function list(): array
    {
        $zipFiles = glob($this->backupPath . DIRECTORY_SEPARATOR . '*.zip') ?: [];
        $backupFiles = glob($this->backupPath . DIRECTORY_SEPARATOR . 'backup_*.*') ?: [];
        $files = array_unique(array_merge($zipFiles, $backupFiles));
        $files = array_filter($files, fn($f) => is_file($f));
        $backups = [];

        foreach ($files as $file) {
            $size = filesize($file);
            $backups[] = [
                'filename'       => basename($file),
                'size'           => $size,
                'size_formatted' => $this->formatBytes($size),
                'created_at'     => date('Y-m-d H:i:s', filemtime($file)),
                'path'           => $file,
            ];
        }

        usort($backups, fn($a, $b) => strcmp($b['filename'], $a['filename']));

        return $backups;
    }

    /**
     * Clean old backups keeping only the most recent $keep backups.
     */
    public function clean(int $keep = 7): int
    {
        $all = $this->list();
        if (count($all) <= $keep) {
            return 0;
        }

        $toDelete = array_slice($all, $keep);
        $deleted = 0;

        foreach ($toDelete as $b) {
            if (@unlink($b['path'])) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Restore database from backup archive.
     */
    public function restore(string $filename): bool
    {
        $filePath = $this->backupPath . DIRECTORY_SEPARATOR . $filename;
        if (!file_exists($filePath)) {
            return false;
        }

        /** @var DatabaseManager $dbManager */
        $dbManager  = app(DatabaseManager::class);
        $connection = $dbManager->connection();
        $dumper     = $this->getDumper($connection);

        // If file is zip, extract database.sql
        if (str_ends_with($filePath, '.zip') && class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                $tempSql = $this->backupPath . DIRECTORY_SEPARATOR . 'restore_temp.sql';
                file_put_contents($tempSql, $zip->getFromName('database.sql'));
                $zip->close();

                $res = $dumper->restore($connection, $tempSql);
                @unlink($tempSql);
                return $res;
            }
            return false;
        }

        return $dumper->restore($connection, $filePath);
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Alias for run().
     */
    public function backup(bool|array $onlyDb = false, ?string $customFilename = null): array
    {
        return $this->run($onlyDb, $customFilename);
    }

    /**
     * Alias for list().
     */
    public function listBackups(): array
    {
        return $this->list();
    }

    /**
     * Alias for clean().
     */
    public function cleanOldBackups(int $keep = 7): int
    {
        return $this->clean($keep);
    }
}
