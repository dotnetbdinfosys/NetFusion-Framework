<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Services/ModuleDataRepository.php
| Namespace : NetFusion\App\Services
| Purpose   : Universal persistent MySQL Database repository for all HMVC modules.
|             Directly manages database persistence for invoices, products, plans,
|             orders, journal entries, leads, employees, tenants, and users with
|             strict audit footprint tracking for any deleted records.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\App\Services;

use PDO;
use PDOException;
use Throwable;

class ModuleDataRepository
{
    /**
     * Cached PDO connection instance.
     */
    protected static ?PDO $pdo = null;

    /**
     * Cache of verified tables in current runtime.
     *
     * @var array<string, bool>
     */
    protected static array $verifiedTables = [];

    /**
     * Get active MySQL PDO connection instance.
     */
    public static function getPdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        // Try resolving through Framework Database Manager
        if (function_exists('db')) {
            try {
                $dbConn = db()->connection();
                if ($dbConn && method_exists($dbConn, 'getPdo')) {
                    $pdo = $dbConn->getPdo();
                    if ($pdo instanceof PDO) {
                        self::$pdo = $pdo;
                        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                        return self::$pdo;
                    }
                }
            } catch (Throwable) {
                // Continue to resilient fallback connections
            }
        }

        // Direct PDO fallback using environment variables and multi-candidate configurations
        $host     = (string) ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1');
        $port     = (string) ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306');
        $database = (string) ($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'netfusion_db');
        $username = (string) ($_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root');
        $password = (string) ($_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '');
        $charset  = (string) ($_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4');

        $pdoOptions = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 3,
        ];

        // Connection Candidates:
        // 1. Configured host and credentials
        // 2. Localhost UNIX socket (standard for Linux/cPanel)
        // 3. Common cPanel database prefix patterns
        // 4. Local root fallback
        $candidates = [
            ['host' => $host, 'port' => $port, 'db' => $database, 'user' => $username, 'pass' => $password],
            ['host' => 'localhost', 'port' => $port, 'db' => $database, 'user' => $username, 'pass' => $password],
            ['host' => '127.0.0.1', 'port' => '3306', 'db' => 'netfusionf_netfusion', 'user' => 'netfusionf', 'pass' => $password],
            ['host' => '127.0.0.1', 'port' => '3306', 'db' => 'netfusionf_db', 'user' => 'netfusionf', 'pass' => $password],
            ['host' => 'localhost', 'port' => '3306', 'db' => 'netfusionf_netfusion', 'user' => 'netfusionf', 'pass' => $password],
            ['host' => '127.0.0.1', 'port' => '3306', 'db' => 'netfusion_db', 'user' => 'root', 'pass' => ''],
            ['host' => 'localhost', 'port' => '3306', 'db' => 'netfusion_db', 'user' => 'root', 'pass' => ''],
        ];

        foreach ($candidates as $cand) {
            try {
                $dsn = "mysql:host={$cand['host']};port={$cand['port']};dbname={$cand['db']};charset={$charset}";
                $pdo = new PDO($dsn, $cand['user'], $cand['pass'], $pdoOptions);
                self::$pdo = $pdo;
                return self::$pdo;
            } catch (Throwable $e) {
                // If error is 1049 (Unknown database), try auto-creating the database
                if (str_contains($e->getMessage(), 'Unknown database') || $e->getCode() === 1049) {
                    try {
                        $adminDsn = "mysql:host={$cand['host']};port={$cand['port']};charset={$charset}";
                        $adminPdo = new PDO($adminDsn, $cand['user'], $cand['pass'], $pdoOptions);
                        $adminPdo->exec("CREATE DATABASE IF NOT EXISTS `{$cand['db']}` CHARACTER SET {$charset} COLLATE utf8mb4_unicode_ci");
                        $dsn = "mysql:host={$cand['host']};port={$cand['port']};dbname={$cand['db']};charset={$charset}";
                        self::$pdo = new PDO($dsn, $cand['user'], $cand['pass'], $pdoOptions);
                        return self::$pdo;
                    } catch (Throwable) {
                        // continue to next candidate
                    }
                }
            }
        }

        // Ultimate Resilient Fallback: SQLite
        // Guarantees zero HTTP 500 crash across any hosting environment
        try {
            $storagePath = function_exists('storage_path') ? storage_path() : (dirname(__DIR__, 2) . '/storage');
            if (!is_dir($storagePath)) {
                @mkdir($storagePath, 0755, true);
            }
            $sqliteFile = $storagePath . '/netfusion.sqlite';
            self::$pdo = new PDO("sqlite:{$sqliteFile}", null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return self::$pdo;
        } catch (Throwable) {
            self::$pdo = new PDO('sqlite::memory:', null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return self::$pdo;
        }
    }

    /**
     * Check if active PDO connection is using SQLite.
     */
    public static function isSqlite(): bool
    {
        try {
            $driver = self::getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
            return strtolower((string) $driver) === 'sqlite';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Resilient SQL execution supporting both MySQL and SQLite schemas.
     */
    public static function executeSql(string $sql): void
    {
        try {
            $pdo = self::getPdo();
            if (self::isSqlite()) {
                $sql = preg_replace('/AUTO_INCREMENT/i', 'AUTOINCREMENT', $sql);
                $sql = preg_replace('/BIGINT\s+AUTOINCREMENT\s+PRIMARY\s+KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
                $sql = preg_replace('/INT\s+AUTOINCREMENT\s+PRIMARY\s+KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
                $sql = preg_replace('/COMMENT\s+\'[^\']*\'/i', '', $sql);
                $sql = preg_replace('/ENGINE\s*=\s*\w+/i', '', $sql);
                $sql = preg_replace('/DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
                $sql = preg_replace('/COLLATE\s*=\s*[\w_]+/i', '', $sql);
                $sql = preg_replace('/,\s*INDEX\s+[^\)]+\)/i', ')', $sql);
            }
            $pdo->exec($sql);
        } catch (Throwable) {
            // Non-blocking schema migration
        }
    }

    /**
     * Ensure the MySQL table exists and seed defaults if empty.
     */
    public static function ensureTable(string $entity): void
    {
        $table = self::resolveTableName($entity);

        if (isset(self::$verifiedTables[$table])) {
            return;
        }

        $pdo = self::getPdo();

        // Ensure audit table exists first with precise time, day, hour, min, sec tracking
        if (!isset(self::$verifiedTables['audit_deleted_records'])) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_deleted_records` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `entity` VARCHAR(100) NOT NULL,
                `record_id` VARCHAR(100) NOT NULL,
                `record_data` LONGTEXT NOT NULL COMMENT 'JSON snapshot of deleted record',
                `deleted_by` VARCHAR(150) NULL COMMENT 'User ID or Name',
                `ip_address` VARCHAR(50) NULL COMMENT 'Client IP',
                `user_agent` TEXT NULL COMMENT 'Client User Agent',
                `request_uri` TEXT NULL COMMENT 'Endpoint URL',
                `request_method` VARCHAR(10) NULL COMMENT 'HTTP Method',
                `deleted_day` VARCHAR(40) NULL COMMENT 'Day of the week e.g. শনিবার (Saturday)',
                `deleted_time` VARCHAR(60) NULL COMMENT 'Exact time with 12h/24h and seconds e.g. 11:15:30 PM [BST]',
                `deleted_at_readable` VARCHAR(160) NULL COMMENT 'Human readable Bengali date and time',
                `created_at` DATETIME NOT NULL COMMENT 'Timestamp of deletion',
                INDEX `idx_entity` (`entity`),
                INDEX `idx_record_id` (`record_id`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Safe migration for existing installations
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM `audit_deleted_records`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('deleted_day', $cols, true)) {
                    $pdo->exec("ALTER TABLE `audit_deleted_records` ADD COLUMN `deleted_day` VARCHAR(40) NULL AFTER `request_method`");
                }
                if (!in_array('deleted_time', $cols, true)) {
                    $pdo->exec("ALTER TABLE `audit_deleted_records` ADD COLUMN `deleted_time` VARCHAR(60) NULL AFTER `deleted_day`");
                }
                if (!in_array('deleted_at_readable', $cols, true)) {
                    $pdo->exec("ALTER TABLE `audit_deleted_records` ADD COLUMN `deleted_at_readable` VARCHAR(160) NULL AFTER `deleted_time`");
                }
            } catch (Throwable $e) {}

            // Ensure cold archive table exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_deleted_records_cold_archive` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `original_audit_id` BIGINT NOT NULL,
                `entity` VARCHAR(100) NOT NULL,
                `record_id` VARCHAR(100) NOT NULL,
                `record_data` LONGTEXT NOT NULL COMMENT 'JSON snapshot of deleted record',
                `deleted_by` VARCHAR(150) NULL,
                `ip_address` VARCHAR(50) NULL,
                `user_agent` TEXT NULL,
                `request_uri` TEXT NULL,
                `request_method` VARCHAR(10) NULL,
                `deleted_day` VARCHAR(40) NULL,
                `deleted_time` VARCHAR(60) NULL,
                `deleted_at_readable` VARCHAR(160) NULL,
                `created_at` DATETIME NOT NULL COMMENT 'Original deletion timestamp',
                `archived_at` DATETIME NOT NULL COMMENT 'Archive timestamp',
                `archived_by` VARCHAR(100) DEFAULT 'system_cron_scheduler',
                `retention_days` INT DEFAULT 180,
                INDEX `idx_cold_entity` (`entity`),
                INDEX `idx_cold_record_id` (`record_id`),
                INDEX `idx_cold_created_at` (`created_at`),
                INDEX `idx_cold_archived_at` (`archived_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            self::$verifiedTables['audit_deleted_records'] = true;
            self::$verifiedTables['audit_deleted_records_cold_archive'] = true;
        }

        $schema = self::getTableSchema($table);
        if ($schema !== null) {
            $pdo->exec($schema);
        } else {
            // Generic dynamic table for custom entities
            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` VARCHAR(100) NOT NULL PRIMARY KEY,
                `data_payload` JSON NULL,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        self::$verifiedTables[$table] = true;

        // Auto-seed defaults if table is empty
        $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        if ($count === 0) {
            $defaults = self::getDefaults($entity);
            if (!empty($defaults)) {
                foreach ($defaults as $record) {
                    self::insertDirect($table, $record);
                }
            }
        }
    }

    /**
     * Resolve entity to table name.
     */
    public static function resolveTableName(string $entity): string
    {
        return match ($entity) {
            'users' => 'system_users',
            default => preg_replace('/[^a-zA-Z0-9_]/', '_', $entity),
        };
    }

    /**
     * Retrieve all records for an entity directly from MySQL.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(string $entity): array
    {
        self::ensureTable($entity);
        $table = self::resolveTableName($entity);
        $pdo = self::getPdo();

        $stmt = $pdo->query("SELECT * FROM `{$table}` ORDER BY `id` ASC");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Auto-cast numerical and JSON fields
        foreach ($results as &$row) {
            self::formatRow($row);
        }
        unset($row);

        return $results;
    }

    /**
     * Find a single record by key and value directly in MySQL.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $entity, string $key, mixed $value): ?array
    {
        self::ensureTable($entity);
        $table = self::resolveTableName($entity);
        $pdo = self::getPdo();

        $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `{$safeKey}` = ? LIMIT 1");
        $stmt->execute([(string) $value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        self::formatRow($row);
        return $row;
    }

    /**
     * Insert a new record into MySQL table.
     *
     * @param array<string, mixed> $record
     */
    public static function insert(string $entity, array $record): bool
    {
        self::ensureTable($entity);
        $table = self::resolveTableName($entity);
        return self::insertDirect($table, $record);
    }

    /**
     * Update an item matching the key/value criteria directly in MySQL.
     *
     * @param array<string, mixed> $newData
     */
    public static function update(string $entity, string $key, mixed $value, array $newData): bool
    {
        self::ensureTable($entity);
        $table = self::resolveTableName($entity);
        $pdo = self::getPdo();

        $tableCols = self::getTableColumns($table);
        $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key);

        $updateFields = [];
        $bindings = [];

        foreach ($newData as $k => $v) {
            if ($k === $key || !in_array($k, $tableCols, true)) {
                continue;
            }
            $updateFields[] = "`{$k}` = ?";
            $bindings[] = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
        }

        if (in_array('updated_at', $tableCols, true) && !isset($newData['updated_at'])) {
            $updateFields[] = "`updated_at` = ?";
            $bindings[] = date('Y-m-d H:i:s');
        }

        if (empty($updateFields)) {
            return true;
        }

        $bindings[] = (string) $value;
        $sql = "UPDATE `{$table}` SET " . implode(', ', $updateFields) . " WHERE `{$safeKey}` = ?";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($bindings);
    }

    /**
     * Delete an item matching the key/value criteria.
     *
     * CRITICAL AUDIT REQUIREMENT:
     * Automatically captures the full snapshot of the record, the user footprint,
     * IP address, user-agent, and endpoint, saving them in `audit_deleted_records`
     * before deleting the record from MySQL.
     *
     * @param array<string, mixed>|null $footprintMeta
     */
    public static function delete(string $entity, string $key, mixed $value, ?array $footprintMeta = null): bool
    {
        self::ensureTable($entity);
        $table = self::resolveTableName($entity);
        $pdo = self::getPdo();

        // 1. Fetch exact record before deletion
        $record = self::find($entity, $key, $value);
        if (!$record) {
            return false;
        }

        // 2. Capture audit footprint
        $recordId = (string) ($record['id'] ?? $value);
        $recordJson = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Resolve user identity
        $deletedBy = $footprintMeta['user'] ?? null;
        if (empty($deletedBy)) {
            if (function_exists('auth')) {
                try {
                    $authUser = auth()->user();
                    if ($authUser) {
                        $deletedBy = $authUser->email ?? $authUser->name ?? $authUser->id ?? null;
                    }
                } catch (Throwable $e) {}
            }
        }
        if (empty($deletedBy)) {
            $deletedBy = $_SESSION['user_email'] ?? $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Admin (System)';
        }

        // Resolve client IP
        $ipAddress = $footprintMeta['ip'] ?? null;
        if (empty($ipAddress)) {
            $ipAddress = $_SERVER['HTTP_CF_CONNECTING_IP']
                ?? $_SERVER['HTTP_X_FORWARDED_FOR']
                ?? $_SERVER['REMOTE_ADDR']
                ?? '127.0.0.1';
            if (str_contains($ipAddress, ',')) {
                $parts = explode(',', $ipAddress);
                $ipAddress = trim($parts[0]);
            }
        }

        // Resolve User Agent
        $userAgent = $footprintMeta['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'NetFusion System Core';

        // Resolve Request URI & Method
        $requestUri = $footprintMeta['request_uri'] ?? $_SERVER['REQUEST_URI'] ?? '/system';
        $requestMethod = $footprintMeta['request_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'DELETE';

        // 3. Compute precise audit time metadata (Day, Date, Hour, Min, Sec in BST and ISO)
        $dt = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Dhaka'));
        $createdAtIso = $dt->format('Y-m-d H:i:s');
        $dayEn = $dt->format('l');
        $daysBn = [
            'Sunday' => 'রবিবার', 'Monday' => 'সোমবার', 'Tuesday' => 'মঙ্গলবার',
            'Wednesday' => 'বুধবার', 'Thursday' => 'বৃহস্পতিবার', 'Friday' => 'শুক্রবার',
            'Saturday' => 'শনিবার'
        ];
        $dayBn = $daysBn[$dayEn] ?? $dayEn;
        $deletedDay = "{$dayBn} ({$dayEn})";

        $time12 = $dt->format('h:i:s A');
        $time24 = $dt->format('H:i:s');
        $deletedTime = "{$time12} [BST / {$time24}]";

        $monthsBn = [
            'January' => 'জানুয়ারি', 'February' => 'ফেব্রুয়ারি', 'March' => 'মার্চ',
            'April' => 'এপ্রিল', 'May' => 'মে', 'June' => 'জুন',
            'July' => 'জুলাই', 'August' => 'আগস্ট', 'September' => 'সেপ্টেম্বর',
            'October' => 'অক্টোবর', 'November' => 'নভেম্বর', 'December' => 'ডিসেম্বর'
        ];
        $monthBn = $monthsBn[$dt->format('F')] ?? $dt->format('F');
        $deletedAtReadable = "{$dayBn}, {$dt->format('d')} {$monthBn} {$dt->format('Y')} — {$time12} (BST / GMT+6)";

        // 4. Store footprint in audit_deleted_records table
        try {
            $auditStmt = $pdo->prepare("INSERT INTO `audit_deleted_records` 
                (`entity`, `record_id`, `record_data`, `deleted_by`, `ip_address`, `user_agent`, `request_uri`, `request_method`, `deleted_day`, `deleted_time`, `deleted_at_readable`, `created_at`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $auditStmt->execute([
                $entity,
                $recordId,
                $recordJson,
                $deletedBy,
                $ipAddress,
                $userAgent,
                $requestUri,
                $requestMethod,
                $deletedDay,
                $deletedTime,
                $deletedAtReadable,
                $createdAtIso,
            ]);
        } catch (Throwable $e) {
            error_log("Failed to write audit footprint: " . $e->getMessage());
        }

        // 5. Perform the deletion from the entity table
        $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
        $delStmt = $pdo->prepare("DELETE FROM `{$table}` WHERE `{$safeKey}` = ?");
        return $delStmt->execute([(string) $value]);
    }

    /**
     * Retrieve audit deleted records footprint log.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAuditDeletedRecords(?string $entity = null, int $limit = 100): array
    {
        try {
            self::ensureTable('audit_deleted_records');
            $pdo = self::getPdo();

            if ($entity !== null && $entity !== '') {
                $stmt = $pdo->prepare("SELECT * FROM `audit_deleted_records` WHERE `entity` = ? ORDER BY `id` DESC LIMIT ?");
                $stmt->bindValue(1, $entity, PDO::PARAM_STR);
                $stmt->bindValue(2, $limit, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $pdo->prepare("SELECT * FROM `audit_deleted_records` ORDER BY `id` DESC LIMIT ?");
                $stmt->bindValue(1, $limit, PDO::PARAM_INT);
                $stmt->execute();
            }

            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($records as &$rec) {
                $rec['parsed_data'] = json_decode((string) ($rec['record_data'] ?? '{}'), true) ?: [];
            }
            unset($rec);

            return $records;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Restore a deleted record from the audit footprint back to its original table.
     * SECURITY POLICY: Strictly restricted to 'super_admin'. Other roles are blocked.
     */
    public static function restoreDeletedRecord(int $auditId, string $operatorRole = 'super_admin'): bool
    {
        // Strict RBAC Enforcement
        if (strtolower(trim($operatorRole)) !== 'super_admin') {
            error_log("SECURITY AUDIT: Unauthorized restore attempt blocked for audit #{$auditId} by non-super_admin role: {$operatorRole}");
            return false;
        }

        self::ensureTable('audit_deleted_records');
        $pdo = self::getPdo();

        $stmt = $pdo->prepare("SELECT * FROM `audit_deleted_records` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$auditId]);
        $audit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$audit) {
            return false;
        }

        $entity = $audit['entity'];
        $data = json_decode((string) $audit['record_data'], true);
        if (!is_array($data)) {
            return false;
        }

        self::ensureTable($entity);
        $table = self::resolveTableName($entity);

        // Re-insert into original table
        return self::insertDirect($table, $data);
    }

    /**
     * Alias for restoreDeletedRecord.
     */
    public static function restoreFromAudit(int $auditId, string $operatorRole = 'super_admin'): bool
    {
        return self::restoreDeletedRecord($auditId, $operatorRole);
    }

    /**
     * Purge or delete audit footprint records.
     * SECURITY POLICY: Strictly restricted to 'super_admin'. Blocked for everyone else.
     *
     * @param int|array<int>|null $ids Null to purge all, or specific ID(s)
     */
    public static function purgeAuditRecords(int|array|null $ids = null, string $operatorRole = ''): bool
    {
        if (strtolower(trim($operatorRole)) !== 'super_admin') {
            error_log("SECURITY AUDIT: Unauthorized audit purge attempt blocked for role: {$operatorRole}");
            return false;
        }

        self::ensureTable('audit_deleted_records');
        $pdo = self::getPdo();

        if ($ids === null) {
            return $pdo->exec("TRUNCATE TABLE `audit_deleted_records`") !== false;
        }

        if (is_int($ids)) {
            $stmt = $pdo->prepare("DELETE FROM `audit_deleted_records` WHERE `id` = ?");
            return $stmt->execute([$ids]);
        }

        if (is_array($ids) && !empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM `audit_deleted_records` WHERE `id` IN ({$placeholders})");
            return $stmt->execute(array_values($ids));
        }

        return false;
    }

    /**
     * Cold Archive Policy: Move old audit logs (e.g. older than 180 days) from hot table to cold storage.
     *
     * @return array{success: bool, archived_count: int, threshold_date: string, archived_by: string, message: string}
     */
    public static function archiveColdAuditRecords(int $daysThreshold = 180, string $operator = 'system_cron_scheduler'): array
    {
        self::ensureTable('audit_deleted_records');
        $pdo = self::getPdo();

        $thresholdDt = new \DateTimeImmutable("-{$daysThreshold} days", new \DateTimeZone('Asia/Dhaka'));
        $threshold = $thresholdDt->format('Y-m-d H:i:s');

        try {
            $pdo->beginTransaction();

            // Select candidate records older than threshold
            $stmt = $pdo->prepare("SELECT * FROM `audit_deleted_records` WHERE `created_at` <= ? FOR UPDATE");
            $stmt->execute([$threshold]);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $archivedCount = count($candidates);

            if ($archivedCount > 0) {
                $insStmt = $pdo->prepare("INSERT INTO `audit_deleted_records_cold_archive` 
                    (`original_audit_id`, `entity`, `record_id`, `record_data`, `deleted_by`, `ip_address`, `user_agent`, `request_uri`, `request_method`, `deleted_day`, `deleted_time`, `deleted_at_readable`, `created_at`, `archived_at`, `archived_by`, `retention_days`) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");

                foreach ($candidates as $row) {
                    $insStmt->execute([
                        $row['id'],
                        $row['entity'],
                        $row['record_id'],
                        $row['record_data'],
                        $row['deleted_by'] ?? null,
                        $row['ip_address'] ?? null,
                        $row['user_agent'] ?? null,
                        $row['request_uri'] ?? null,
                        $row['request_method'] ?? null,
                        $row['deleted_day'] ?? null,
                        $row['deleted_time'] ?? null,
                        $row['deleted_at_readable'] ?? null,
                        $row['created_at'],
                        $operator,
                        $daysThreshold,
                    ]);
                }

                // Delete archived records from active audit table
                $delStmt = $pdo->prepare("DELETE FROM `audit_deleted_records` WHERE `created_at` <= ?");
                $delStmt->execute([$threshold]);
            }

            $pdo->commit();

            return [
                'success'        => true,
                'archived_count' => $archivedCount,
                'threshold_date' => $threshold,
                'archived_by'    => $operator,
                'message'        => "{$archivedCount} টি পুরনো অডিট লগ সফলভাবে কোল্ড আর্কাইভে স্থানান্তরিত হয়েছে (সীমা: {$daysThreshold} দিন)।",
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Cold Archive Error: " . $e->getMessage());
            return [
                'success'        => false,
                'archived_count' => 0,
                'threshold_date' => $threshold,
                'archived_by'    => $operator,
                'message'        => "কোল্ড আর্কাইভ প্রক্রিয়াকরণে ত্রুটি: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve cold archived audit logs.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getColdArchivedRecords(?string $entity = null, int $limit = 50): array
    {
        try {
            self::ensureTable('audit_deleted_records');
            $pdo = self::getPdo();

            if ($entity !== null && $entity !== '') {
                $stmt = $pdo->prepare("SELECT * FROM `audit_deleted_records_cold_archive` WHERE `entity` = ? ORDER BY `id` DESC LIMIT ?");
                $stmt->bindValue(1, $entity, PDO::PARAM_STR);
                $stmt->bindValue(2, $limit, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $pdo->prepare("SELECT * FROM `audit_deleted_records_cold_archive` ORDER BY `id` DESC LIMIT ?");
                $stmt->bindValue(1, $limit, PDO::PARAM_INT);
                $stmt->execute();
            }

            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($records as &$rec) {
                $rec['parsed_data'] = json_decode((string) ($rec['record_data'] ?? '{}'), true) ?: [];
            }
            unset($rec);

            return $records;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Legacy save method for compatibility - synchronizes with MySQL.
     * NOTE: Does NOT write to JSON files.
     *
     * @param array<int, array<string, mixed>> $items
     */
    public static function save(string $entity, array $items): bool
    {
        self::ensureTable($entity);
        $table = self::resolveTableName($entity);

        foreach ($items as $item) {
            if (is_array($item) && isset($item['id'])) {
                $existing = self::find($entity, 'id', $item['id']);
                if ($existing) {
                    self::update($entity, 'id', $item['id'], $item);
                } else {
                    self::insertDirect($table, $item);
                }
            }
        }
        return true;
    }

    /**
     * Internal direct insert with column mapping.
     */
    protected static function insertDirect(string $table, array $record): bool
    {
        $pdo = self::getPdo();
        $tableCols = self::getTableColumns($table);

        $insertData = [];
        foreach ($tableCols as $col) {
            if (array_key_exists($col, $record)) {
                $val = $record[$col];
                $insertData[$col] = is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : $val;
            }
        }

        if (in_array('created_at', $tableCols, true) && !isset($insertData['created_at'])) {
            $insertData['created_at'] = date('Y-m-d H:i:s');
        }
        if (in_array('updated_at', $tableCols, true) && !isset($insertData['updated_at'])) {
            $insertData['updated_at'] = date('Y-m-d H:i:s');
        }

        if (empty($insertData['id']) && in_array('id', $tableCols, true)) {
            $insertData['id'] = uniqid(substr($table, 0, 4) . '_');
        }

        $cols = array_keys($insertData);
        $fieldsStr = implode('`, `', $cols);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO `{$table}` (`{$fieldsStr}`) VALUES ({$placeholders})";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute(array_values($insertData));
        } catch (PDOException $e) {
            // If duplicate key, try updating
            if (isset($insertData['id'])) {
                $id = $insertData['id'];
                unset($insertData['id']);
                $updateCols = [];
                $updateVals = [];
                foreach ($insertData as $k => $v) {
                    $updateCols[] = "`{$k}` = ?";
                    $updateVals[] = $v;
                }
                $updateVals[] = $id;
                $updateSql = "UPDATE `{$table}` SET " . implode(', ', $updateCols) . " WHERE `id` = ?";
                $stmt = $pdo->prepare($updateSql);
                return $stmt->execute($updateVals);
            }
            return false;
        }
    }

    /**
     * Get table columns list.
     *
     * @return array<int, string>
     */
    protected static function getTableColumns(string $table): array
    {
        try {
            $pdo = self::getPdo();
            if (self::isSqlite()) {
                $stmt = $pdo->query("PRAGMA table_info(\"{$table}\")");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return array_column($rows, 'name');
            }
            $stmt = $pdo->query("DESCRIBE `{$table}`");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Format row types (cast numeric strings back to float/int if needed).
     */
    protected static function formatRow(array &$row): void
    {
        foreach ($row as $key => $val) {
            if (is_numeric($val) && in_array($key, ['amount', 'price', 'cost_price', 'selling_price', 'salary', 'value', 'debit_amount', 'credit_amount', 'total_amount'])) {
                $row[$key] = (float) $val;
            } elseif (is_numeric($val) && in_array($key, ['stock', 'items_count', 'users'])) {
                $row[$key] = (int) $val;
            }
        }
    }

    /**
     * Get explicit table schema SQL.
     */
    protected static function getTableSchema(string $table): ?string
    {
        return match ($table) {
            // ---------------------------------------------------------
            // 1. BILLING MODULE
            // ---------------------------------------------------------
            'billing_customers' => "CREATE TABLE IF NOT EXISTS `billing_customers` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `tenant_id` VARCHAR(100) NULL,
                `name` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(50) NULL,
                `company_name` VARCHAR(255) NULL,
                `billing_address` TEXT NULL,
                `tax_id` VARCHAR(100) NULL,
                `status` VARCHAR(50) DEFAULT 'active',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                INDEX `idx_cust_tenant` (`tenant_id`),
                INDEX `idx_cust_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'billing_invoice_items' => "CREATE TABLE IF NOT EXISTS `billing_invoice_items` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `invoice_id` VARCHAR(50) NOT NULL,
                `item_name` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `quantity` DECIMAL(10,2) DEFAULT 1.00,
                `unit_price` DECIMAL(15,2) DEFAULT 0.00,
                `tax_rate` DECIMAL(5,2) DEFAULT 0.00,
                `line_total` DECIMAL(15,2) DEFAULT 0.00,
                `created_at` DATETIME NULL,
                INDEX `idx_item_invoice` (`invoice_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'billing_payments' => "CREATE TABLE IF NOT EXISTS `billing_payments` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `invoice_id` VARCHAR(50) NOT NULL,
                `customer_id` VARCHAR(50) NULL,
                `transaction_ref` VARCHAR(100) NOT NULL,
                `gateway` VARCHAR(100) DEFAULT 'নগদ / বিকাশ',
                `payment_method` VARCHAR(100) DEFAULT 'bKash Online',
                `amount` DECIMAL(15,2) DEFAULT 0.00,
                `currency` VARCHAR(10) DEFAULT 'BDT',
                `payment_date` DATETIME NOT NULL,
                `status` VARCHAR(50) DEFAULT 'success',
                `notes` TEXT NULL,
                `created_at` DATETIME NULL,
                INDEX `idx_pay_invoice` (`invoice_id`),
                INDEX `idx_pay_customer` (`customer_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'billing_invoices' => "CREATE TABLE IF NOT EXISTS `billing_invoices` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `customer` VARCHAR(255) NOT NULL,
                `plan` VARCHAR(150) NOT NULL,
                `cycle` VARCHAR(50) DEFAULT 'মাসিক',
                `amount` DECIMAL(15,2) DEFAULT 0.00,
                `method` VARCHAR(100) DEFAULT 'নগদ',
                `issue_date` DATE NULL,
                `due_date` DATE NULL,
                `status` VARCHAR(50) DEFAULT 'pending',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'billing_plans' => "CREATE TABLE IF NOT EXISTS `billing_plans` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `price` DECIMAL(15,2) DEFAULT 0.00,
                `interval` VARCHAR(50) DEFAULT 'মাসিক',
                `users` VARCHAR(100) NULL,
                `features` TEXT NULL,
                `status` VARCHAR(50) DEFAULT 'active',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'billing_subscriptions' => "CREATE TABLE IF NOT EXISTS `billing_subscriptions` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `customer` VARCHAR(255) NOT NULL,
                `plan` VARCHAR(150) NOT NULL,
                `start_date` DATE NULL,
                `renewal_date` DATE NULL,
                `auto_renew` VARCHAR(50) DEFAULT 'চালু (Yes)',
                `status` VARCHAR(50) DEFAULT 'active',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------
            // 2. INVENTORY MODULE
            // ---------------------------------------------------------
            'inventory_categories' => "CREATE TABLE IF NOT EXISTS `inventory_categories` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `category_code` VARCHAR(50) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `parent_id` VARCHAR(50) NULL,
                `description` TEXT NULL,
                `status` VARCHAR(50) DEFAULT 'active',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                INDEX `idx_cat_parent` (`parent_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'inventory_suppliers' => "CREATE TABLE IF NOT EXISTS `inventory_suppliers` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `supplier_code` VARCHAR(50) NOT NULL,
                `company_name` VARCHAR(255) NOT NULL,
                `contact_person` VARCHAR(150) NULL,
                `phone` VARCHAR(50) NULL,
                `email` VARCHAR(255) NULL,
                `address` TEXT NULL,
                `status` VARCHAR(50) DEFAULT 'active',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'inventory_stock_movements' => "CREATE TABLE IF NOT EXISTS `inventory_stock_movements` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `reference_no` VARCHAR(100) NOT NULL,
                `product_id` VARCHAR(50) NOT NULL,
                `source_warehouse_id` VARCHAR(50) NULL,
                `target_warehouse_id` VARCHAR(50) NULL,
                `movement_type` VARCHAR(50) NOT NULL COMMENT 'in, out, transfer, adjustment',
                `quantity` INT NOT NULL,
                `unit_cost` DECIMAL(15,2) DEFAULT 0.00,
                `notes` TEXT NULL,
                `performed_by` VARCHAR(150) NULL,
                `created_at` DATETIME NOT NULL,
                INDEX `idx_move_prod` (`product_id`),
                INDEX `idx_move_src` (`source_warehouse_id`),
                INDEX `idx_move_tgt` (`target_warehouse_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'inventory_products' => "CREATE TABLE IF NOT EXISTS `inventory_products` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `sku` VARCHAR(100) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `category` VARCHAR(150) NULL,
                `unit` VARCHAR(50) DEFAULT 'পিস (Pcs)',
                `cost_price` DECIMAL(15,2) DEFAULT 0.00,
                `selling_price` DECIMAL(15,2) DEFAULT 0.00,
                `stock` INT DEFAULT 0,
                `warehouse` VARCHAR(150) NULL,
                `status` VARCHAR(50) DEFAULT 'in_stock',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'inventory_warehouses' => "CREATE TABLE IF NOT EXISTS `inventory_warehouses` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `code` VARCHAR(50) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `incharge` VARCHAR(150) NULL,
                `location` TEXT NULL,
                `capacity` VARCHAR(150) NULL,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------
            // 3. ACCOUNTING MODULE
            // ---------------------------------------------------------
            'accounting_fiscal_years' => "CREATE TABLE IF NOT EXISTS `accounting_fiscal_years` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `year_title` VARCHAR(100) NOT NULL,
                `start_date` DATE NOT NULL,
                `end_date` DATE NOT NULL,
                `is_closed` TINYINT(1) DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'accounting_chart_of_accounts' => "CREATE TABLE IF NOT EXISTS `accounting_chart_of_accounts` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `account_code` VARCHAR(50) NOT NULL UNIQUE,
                `account_name` VARCHAR(255) NOT NULL,
                `account_type` VARCHAR(50) NOT NULL COMMENT 'asset, liability, equity, revenue, expense',
                `parent_code` VARCHAR(50) NULL,
                `normal_balance` VARCHAR(10) DEFAULT 'debit',
                `opening_balance` DECIMAL(15,2) DEFAULT 0.00,
                `current_balance` DECIMAL(15,2) DEFAULT 0.00,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                INDEX `idx_coa_type` (`account_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'accounting_voucher_entries' => "CREATE TABLE IF NOT EXISTS `accounting_voucher_entries` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `voucher_id` VARCHAR(50) NOT NULL,
                `account_id` VARCHAR(50) NOT NULL,
                `entry_description` TEXT NULL,
                `debit_amount` DECIMAL(15,2) DEFAULT 0.00,
                `credit_amount` DECIMAL(15,2) DEFAULT 0.00,
                `created_at` DATETIME NULL,
                INDEX `idx_entry_voucher` (`voucher_id`),
                INDEX `idx_entry_account` (`account_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'accounting_journal' => "CREATE TABLE IF NOT EXISTS `accounting_journal` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `voucher_no` VARCHAR(50) NOT NULL,
                `date` DATE NULL,
                `debit_account` VARCHAR(255) NOT NULL,
                `credit_account` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `debit_amount` DECIMAL(15,2) DEFAULT 0.00,
                `credit_amount` DECIMAL(15,2) DEFAULT 0.00,
                `status` VARCHAR(50) DEFAULT 'posted',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------
            // 4. COMMERCE MODULE
            // ---------------------------------------------------------
            'commerce_customers' => "CREATE TABLE IF NOT EXISTS `commerce_customers` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(50) NULL,
                `shipping_address` TEXT NULL,
                `city` VARCHAR(100) NULL,
                `loyalty_points` INT DEFAULT 0,
                `status` VARCHAR(50) DEFAULT 'active',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'commerce_coupons' => "CREATE TABLE IF NOT EXISTS `commerce_coupons` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `coupon_code` VARCHAR(50) NOT NULL UNIQUE,
                `discount_type` VARCHAR(20) DEFAULT 'percent',
                `discount_value` DECIMAL(10,2) NOT NULL,
                `min_order_amount` DECIMAL(15,2) DEFAULT 0.00,
                `max_discount` DECIMAL(15,2) NULL,
                `starts_at` DATETIME NULL,
                `expires_at` DATETIME NULL,
                `usage_limit` INT DEFAULT 100,
                `usage_count` INT DEFAULT 0,
                `status` VARCHAR(50) DEFAULT 'active',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'commerce_order_items' => "CREATE TABLE IF NOT EXISTS `commerce_order_items` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `order_id` VARCHAR(50) NOT NULL,
                `product_id` VARCHAR(50) NULL,
                `product_name` VARCHAR(255) NOT NULL,
                `sku` VARCHAR(100) NULL,
                `unit_price` DECIMAL(15,2) NOT NULL,
                `quantity` INT DEFAULT 1,
                `line_total` DECIMAL(15,2) NOT NULL,
                `created_at` DATETIME NULL,
                INDEX `idx_orditem_order` (`order_id`),
                INDEX `idx_orditem_prod` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'commerce_orders' => "CREATE TABLE IF NOT EXISTS `commerce_orders` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `order_code` VARCHAR(50) NOT NULL,
                `customer` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(50) NULL,
                `items_count` INT DEFAULT 1,
                `total_amount` DECIMAL(15,2) DEFAULT 0.00,
                `gateway` VARCHAR(100) NULL,
                `shipping_status` VARCHAR(50) DEFAULT 'pending',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------
            // 5. CRM MODULE
            // ---------------------------------------------------------
            'crm_companies' => "CREATE TABLE IF NOT EXISTS `crm_companies` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `company_name` VARCHAR(255) NOT NULL,
                `industry` VARCHAR(150) NULL,
                `website` VARCHAR(255) NULL,
                `phone` VARCHAR(50) NULL,
                `email` VARCHAR(255) NULL,
                `address` TEXT NULL,
                `annual_revenue` DECIMAL(15,2) DEFAULT 0.00,
                `status` VARCHAR(50) DEFAULT 'active',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'crm_contacts' => "CREATE TABLE IF NOT EXISTS `crm_contacts` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `company_id` VARCHAR(50) NULL,
                `first_name` VARCHAR(150) NOT NULL,
                `last_name` VARCHAR(150) NULL,
                `designation` VARCHAR(150) NULL,
                `email` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(50) NULL,
                `is_primary` TINYINT(1) DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                INDEX `idx_contact_comp` (`company_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'crm_deals' => "CREATE TABLE IF NOT EXISTS `crm_deals` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `deal_code` VARCHAR(50) NOT NULL,
                `lead_id` VARCHAR(50) NULL,
                `company_id` VARCHAR(50) NULL,
                `deal_name` VARCHAR(255) NOT NULL,
                `deal_amount` DECIMAL(15,2) DEFAULT 0.00,
                `pipeline_stage` VARCHAR(100) DEFAULT 'Discovery',
                `closing_date` DATE NULL,
                `probability_pct` INT DEFAULT 50,
                `status` VARCHAR(50) DEFAULT 'open',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                INDEX `idx_deal_lead` (`lead_id`),
                INDEX `idx_deal_comp` (`company_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'crm_tickets' => "CREATE TABLE IF NOT EXISTS `crm_tickets` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `ticket_no` VARCHAR(50) NOT NULL UNIQUE,
                `contact_id` VARCHAR(50) NULL,
                `subject` VARCHAR(255) NOT NULL,
                `priority` VARCHAR(50) DEFAULT 'medium',
                `status` VARCHAR(50) DEFAULT 'open',
                `assigned_agent` VARCHAR(150) NULL,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                INDEX `idx_ticket_contact` (`contact_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'crm_leads' => "CREATE TABLE IF NOT EXISTS `crm_leads` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `company` VARCHAR(255) NULL,
                `value` DECIMAL(15,2) DEFAULT 0.00,
                `stage` VARCHAR(100) DEFAULT 'Lead',
                `phone` VARCHAR(50) NULL,
                `status` VARCHAR(50) DEFAULT 'active',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------
            // 6. HRM MODULE
            // ---------------------------------------------------------
            'hrm_departments' => "CREATE TABLE IF NOT EXISTS `hrm_departments` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `department_code` VARCHAR(50) NOT NULL,
                `department_name` VARCHAR(255) NOT NULL,
                `head_name` VARCHAR(150) NULL,
                `location` VARCHAR(255) NULL,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'hrm_designations' => "CREATE TABLE IF NOT EXISTS `hrm_designations` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `department_id` VARCHAR(50) NULL,
                `designation_title` VARCHAR(255) NOT NULL,
                `grade_level` VARCHAR(50) NULL,
                `min_salary` DECIMAL(15,2) DEFAULT 0.00,
                `max_salary` DECIMAL(15,2) DEFAULT 0.00,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                INDEX `idx_desig_dept` (`department_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'hrm_attendance' => "CREATE TABLE IF NOT EXISTS `hrm_attendance` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `employee_id` VARCHAR(50) NOT NULL,
                `attendance_date` DATE NOT NULL,
                `clock_in` TIME NULL,
                `clock_out` TIME NULL,
                `working_hours` DECIMAL(5,2) DEFAULT 8.00,
                `attendance_status` VARCHAR(50) DEFAULT 'present',
                `created_at` DATETIME NULL,
                INDEX `idx_att_emp` (`employee_id`),
                INDEX `idx_att_date` (`attendance_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'hrm_payroll' => "CREATE TABLE IF NOT EXISTS `hrm_payroll` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `payroll_no` VARCHAR(50) NOT NULL,
                `employee_id` VARCHAR(50) NOT NULL,
                `salary_month` VARCHAR(20) NOT NULL,
                `basic_salary` DECIMAL(15,2) NOT NULL,
                `allowance_amount` DECIMAL(15,2) DEFAULT 0.00,
                `deduction_amount` DECIMAL(15,2) DEFAULT 0.00,
                `net_payable` DECIMAL(15,2) NOT NULL,
                `payment_method` VARCHAR(100) DEFAULT 'Bank Transfer',
                `payment_status` VARCHAR(50) DEFAULT 'paid',
                `payment_date` DATE NULL,
                `created_at` DATETIME NULL,
                INDEX `idx_pay_emp` (`employee_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'hrm_employees' => "CREATE TABLE IF NOT EXISTS `hrm_employees` (
                `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `department` VARCHAR(150) NOT NULL,
                `designation` VARCHAR(150) NOT NULL,
                `salary` DECIMAL(15,2) DEFAULT 0.00,
                `status` VARCHAR(50) DEFAULT 'active',
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------
            // 7. SYSTEM & TENANCY MODULE
            // ---------------------------------------------------------
            'tenants' => "CREATE TABLE IF NOT EXISTS `tenants` (
                `id` VARCHAR(100) NOT NULL PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `site_title` VARCHAR(255) NULL,
                `description` TEXT NULL,
                `domain` VARCHAR(255) NULL,
                `driver` VARCHAR(50) DEFAULT 'database',
                `status` VARCHAR(50) DEFAULT 'Active',
                `users` INT DEFAULT 0,
                `created_at` VARCHAR(50) NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'system_users' => "CREATE TABLE IF NOT EXISTS `system_users` (
                `id` VARCHAR(100) NOT NULL PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `password_hash` VARCHAR(255) NULL,
                `role` VARCHAR(50) NOT NULL,
                `tenant_id` VARCHAR(100) NULL,
                `avatar_url` VARCHAR(255) NULL,
                `phone` VARCHAR(50) NULL,
                `bio` TEXT NULL,
                `last_login_at` DATETIME NULL,
                `status` VARCHAR(50) DEFAULT 'Active',
                `created_at` VARCHAR(50) NULL,
                `updated_at` DATETIME NULL,
                INDEX `idx_usr_email` (`email`),
                INDEX `idx_usr_tenant` (`tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'site_settings' => "CREATE TABLE IF NOT EXISTS `site_settings` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `key_name` VARCHAR(100) UNIQUE NOT NULL,
                `value_data` LONGTEXT NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'modules_state' => "CREATE TABLE IF NOT EXISTS `modules_state` (
                `module_name` VARCHAR(100) NOT NULL PRIMARY KEY,
                `enabled` TINYINT(1) DEFAULT 1,
                `state_payload` JSON NULL,
                `updated_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            default => null,
        };
    }

    /**
     * Get default initial dataset for any entity.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getDefaults(string $entity): array
    {
        return match ($entity) {
            'billing_invoices' => [
                [
                    'id' => 'INV-781',
                    'customer' => 'ঢাকা টেক সল্যুশনস লিঃ',
                    'plan' => 'Enterprise Suite (বার্ষিক)',
                    'cycle' => 'বার্ষিক',
                    'amount' => 119988.00,
                    'method' => 'সিটি ব্যাংক কার্ড',
                    'issue_date' => '2026-10-14',
                    'due_date' => '2026-10-24',
                    'status' => 'paid',
                ],
                [
                    'id' => 'INV-780',
                    'customer' => 'ব্লু-স্কাই সফটওয়্যার লিমিটেড',
                    'plan' => 'Professional Pro (মাসিক)',
                    'cycle' => 'মাসিক',
                    'amount' => 4999.00,
                    'method' => 'বিকাশ গেটওয়ে',
                    'issue_date' => '2026-10-14',
                    'due_date' => '2026-10-21',
                    'status' => 'paid',
                ],
                [
                    'id' => 'INV-779',
                    'customer' => 'প্রাইম লজিস্টিকস কর্পোরেশন',
                    'plan' => 'Enterprise Suite (মাসিক)',
                    'cycle' => 'মাসিক',
                    'amount' => 9999.00,
                    'method' => 'নগদ পেমেন্ট',
                    'issue_date' => '2026-10-13',
                    'due_date' => '2026-10-20',
                    'status' => 'pending',
                ],
                [
                    'id' => 'INV-778',
                    'customer' => 'স্মার্ট ফ্যাশনস ডট কম',
                    'plan' => 'Professional Pro (মাসিক)',
                    'cycle' => 'মাসিক',
                    'amount' => 4999.00,
                    'method' => 'ভিসা কার্ড',
                    'issue_date' => '2026-10-01',
                    'due_date' => '2026-10-08',
                    'status' => 'overdue',
                ],
                [
                    'id' => 'INV-777',
                    'customer' => 'মেঘনা গ্রুপ আইটি ডিপার্টমেন্ট',
                    'plan' => 'Enterprise Multi-Tenant',
                    'cycle' => 'মাসিক',
                    'amount' => 24999.00,
                    'method' => 'ব্যাংক ট্রান্সফার',
                    'issue_date' => '2026-10-12',
                    'due_date' => '2026-10-19',
                    'status' => 'paid',
                ],
            ],
            'billing_plans' => [
                [
                    'id' => 'starter',
                    'name' => 'Starter Tier',
                    'price' => 1999.00,
                    'interval' => 'মাসিক',
                    'users' => '৫ জন ইউজার অ্যাক্সেস',
                    'features' => '১টি টেন্যান্ট ডেটাবেস, বেসিক অ্যাকাউন্টিং ও ইনভয়েস, ইমেইল সাপোর্ট',
                    'status' => 'active',
                ],
                [
                    'id' => 'pro',
                    'name' => 'Professional Pro',
                    'price' => 4999.00,
                    'interval' => 'মাসিক',
                    'users' => '২৫ জন ইউজার অ্যাক্সেস',
                    'features' => '৫টি ব্রাঞ্চ সাপোর্ট, অ্যাকাউন্টিং, বিলিং, সিআরএম মডিউল, প্রায়োরিটি সাপোর্ট',
                    'status' => 'active',
                ],
                [
                    'id' => 'enterprise',
                    'name' => 'Enterprise Suite',
                    'price' => 9999.00,
                    'interval' => 'মাসিক',
                    'users' => 'আনলিমিটেড ইউজার ও রোলস',
                    'features' => 'মাল্টি-টেন্যান্ট আইসোলেশন ইঞ্জিন, সকল HMVC মডিউল সক্রিয়, ২৪/৭ ডেডিকেটেড এসএলএ',
                    'status' => 'active',
                ],
                [
                    'id' => 'custom',
                    'name' => 'Custom Enterprise',
                    'price' => 24999.00,
                    'interval' => 'কাস্টম',
                    'users' => 'কাস্টম ডেডিকেটেড ক্লাস্টার',
                    'features' => 'অন-প্রিমিসেস ডেডিকেটেড ক্লাস্টার, কাস্টম মডিউল ডেভেলপমেন্ট, হোয়াইট-লেবেল ব্রান্ডিং',
                    'status' => 'active',
                ],
            ],
            'billing_subscriptions' => [
                [
                    'id' => 'SUB-0101',
                    'customer' => 'ঢাকা টেক সল্যুশনস লিঃ',
                    'plan' => 'Enterprise Suite',
                    'start_date' => '2026-01-01',
                    'renewal_date' => '2027-01-01',
                    'auto_renew' => 'চালু (Yes)',
                    'status' => 'active',
                ],
                [
                    'id' => 'SUB-0102',
                    'customer' => 'ব্লু-স্কাই সফটওয়্যার লিমিটেড',
                    'plan' => 'Professional Pro',
                    'start_date' => '2026-10-14',
                    'renewal_date' => '2026-11-14',
                    'auto_renew' => 'চালু (Yes)',
                    'status' => 'active',
                ],
                [
                    'id' => 'SUB-0103',
                    'customer' => 'প্রাইম লজিস্টিকস কর্পোরেশন',
                    'plan' => 'Enterprise Suite',
                    'start_date' => '2026-10-13',
                    'renewal_date' => '2026-11-13',
                    'auto_renew' => 'চালু (Yes)',
                    'status' => 'grace_period',
                ],
                [
                    'id' => 'SUB-0104',
                    'customer' => 'গ্রীন ডেল্টা করপোরেট',
                    'plan' => 'Starter Tier',
                    'start_date' => '2026-08-01',
                    'renewal_date' => '2026-11-01',
                    'auto_renew' => 'বন্ধ (No)',
                    'status' => 'active',
                ],
            ],
            'inventory_products' => [
                [
                    'id' => 'AUD-901',
                    'sku' => 'AUD-901',
                    'name' => 'প্রিমিয়াম ওয়্যারলেস হেডসেট',
                    'category' => 'অডিও এক্সেসরিজ',
                    'unit' => 'পিস (Pcs)',
                    'cost_price' => 2400.00,
                    'selling_price' => 3200.00,
                    'stock' => 268,
                    'warehouse' => 'তেজগাঁও - A1',
                    'status' => 'in_stock',
                ],
                [
                    'id' => 'WTB-204',
                    'sku' => 'WTB-204',
                    'name' => 'স্মার্ট ট্র্যাকিং ফিটনেস ব্যান্ড',
                    'category' => 'স্মার্ট ডিভাইস',
                    'unit' => 'পিস (Pcs)',
                    'cost_price' => 1500.00,
                    'selling_price' => 2100.00,
                    'stock' => 145,
                    'warehouse' => 'তেজগাঁও - B3',
                    'status' => 'in_stock',
                ],
                [
                    'id' => 'PWR-65W',
                    'sku' => 'PWR-65W',
                    'name' => 'টাইপ-সি আল্ট্রাফাস্ট চার্জার ৬৫W',
                    'category' => 'মোবাইল পাওয়ার',
                    'unit' => 'পিস (Pcs)',
                    'cost_price' => 950.00,
                    'selling_price' => 1450.00,
                    'stock' => 8,
                    'warehouse' => 'চট্টগ্রাম - C2',
                    'status' => 'low_stock',
                ],
                [
                    'id' => 'CBL-HDMI',
                    'sku' => 'CBL-HDMI',
                    'name' => 'এইচডিএমআই আল্ট্রা ক্যাবল ৩মি',
                    'category' => 'ক্যাবল ও কানেক্টর',
                    'unit' => 'পিস (Pcs)',
                    'cost_price' => 450.00,
                    'selling_price' => 750.00,
                    'stock' => 12,
                    'warehouse' => 'তেজগাঁও - D1',
                    'status' => 'low_stock',
                ],
            ],
            'inventory_warehouses' => [
                [
                    'id' => 'WH-01',
                    'code' => 'WH-01',
                    'name' => 'তেজগাঁও সেন্ট্রাল ওয়্যারহাউস',
                    'incharge' => 'আতিকুর রহমান',
                    'location' => 'তেজগাঁও শিল্পাঞ্চল, ঢাকা',
                    'capacity' => '৮৫% পূর্ণ (৪,২০০ স্কয়ারফুট)',
                ],
                [
                    'id' => 'WH-02',
                    'code' => 'WH-02',
                    'name' => 'চট্টগ্রাম পোর্ট ডিস্ট্রিবিউশন হাব',
                    'incharge' => 'মাহমুদুল হাসান',
                    'location' => 'আগ্রাবাদ, চট্টগ্রাম',
                    'capacity' => '৬২% পূর্ণ (৩,০০০ স্কয়ারফুট)',
                ],
            ],
            'accounting_journal' => [
                [
                    'id' => 'JV-2026-092',
                    'voucher_no' => 'JV-2026-092',
                    'date' => '2026-10-15',
                    'debit_account' => '1010-01 সিটি ব্যাংক',
                    'credit_account' => '4010-01 সফটওয়্যার সেলস',
                    'description' => 'ইনভয়েস #104 এর বিপরীতে সফটওয়্যার ডেলিভারি বাবদ পূর্ণ অর্থ প্রাপ্তি।',
                    'debit_amount' => 50000.00,
                    'credit_amount' => 50000.00,
                    'status' => 'posted',
                ],
                [
                    'id' => 'JV-2026-091',
                    'voucher_no' => 'JV-2026-091',
                    'date' => '2026-10-14',
                    'debit_account' => '5010-01 সার্ভার খরচ',
                    'credit_account' => '1010-01 সিটি ব্যাংক',
                    'description' => 'AWS ক্লাউড ডেডিকেটেড সার্ভার মাসিক ইনফ্রাস্ট্রাকচার চার্জ পরিশোধ।',
                    'debit_amount' => 12400.00,
                    'credit_amount' => 12400.00,
                    'status' => 'posted',
                ],
                [
                    'id' => 'JV-2026-090',
                    'voucher_no' => 'JV-2026-090',
                    'date' => '2026-10-13',
                    'debit_account' => '5020-01 অফিস স্টেশনারি',
                    'credit_account' => '1010-03 পেটি ক্যাশ',
                    'description' => 'প্রিন্টার টোনার ও অফিস পেপার ক্রয় বাবদ ক্যাশ ভাউচার অনুমোদন।',
                    'debit_amount' => 4200.00,
                    'credit_amount' => 4200.00,
                    'status' => 'posted',
                ],
            ],
            'commerce_orders' => [
                [
                    'id' => 'ORD-904',
                    'order_code' => 'ORD-904',
                    'customer' => 'সাদিয়া আফরিন',
                    'phone' => '+880 1711-234567',
                    'items_count' => 2,
                    'total_amount' => 5700.00,
                    'gateway' => 'বিকাশ অনলাইন',
                    'shipping_status' => 'delivered',
                ],
                [
                    'id' => 'ORD-903',
                    'order_code' => 'ORD-903',
                    'customer' => 'মেহরাব হোসেন',
                    'phone' => '+880 1819-876543',
                    'items_count' => 1,
                    'total_amount' => 2200.00,
                    'gateway' => 'ভিসা কার্ড',
                    'shipping_status' => 'shipped',
                ],
                [
                    'id' => 'ORD-902',
                    'order_code' => 'ORD-902',
                    'customer' => 'রাশেদুল করিম',
                    'phone' => '+880 1912-345678',
                    'items_count' => 3,
                    'total_amount' => 4350.00,
                    'gateway' => 'ক্যাশ অন ডেলিভারি',
                    'shipping_status' => 'processing',
                ],
            ],
            'crm_leads' => [
                [
                    'id' => 'LD-101',
                    'name' => 'তানভীর আহমেদ',
                    'company' => 'ইনোভেক্স আইটি সলিউশনস',
                    'value' => 150000.00,
                    'stage' => 'Proposal Sent',
                    'phone' => '+880 1712-345678',
                    'status' => 'active',
                ],
                [
                    'id' => 'LD-102',
                    'name' => 'ফারহানা ইসলাম',
                    'company' => 'গ্লোবাল লজিস্টিকস',
                    'value' => 280000.00,
                    'stage' => 'Negotiation',
                    'phone' => '+880 1812-345678',
                    'status' => 'active',
                ],
            ],
            'hrm_employees' => [
                [
                    'id' => 'EMP-01',
                    'name' => 'আরিফুল ইসলাম',
                    'department' => 'সফটওয়্যার ইঞ্জিনিয়ারিং',
                    'designation' => 'সিনিয়র পিএইচপি ডেভেলপার',
                    'salary' => 85000.00,
                    'status' => 'active',
                ],
                [
                    'id' => 'EMP-02',
                    'name' => 'নুসরাত জাহান',
                    'department' => 'কোয়ালিটি অ্যাস্যুরেন্স',
                    'designation' => 'কিউএ লিড',
                    'salary' => 65000.00,
                    'status' => 'active',
                ],
            ],
            'tenants' => [
                [
                    'id' => 'tenant_primary',
                    'name' => 'DotNetBD Corporate HQ',
                    'site_title' => 'DotNetBD Corporate Hub',
                    'description' => 'Primary headquarters tenant for enterprise operations, financial consolidation, and executive governance.',
                    'domain' => 'corporate.nff.dotnetbd.com',
                    'driver' => 'database',
                    'status' => 'Active',
                    'users' => 24,
                    'created_at' => '2026-01-10',
                ],
                [
                    'id' => 'tenant_enterprise',
                    'name' => 'Apex Enterprise Global',
                    'site_title' => 'Apex Global Commerce Portal',
                    'description' => 'Multinational supply-chain and global distribution channel for Apex Enterprise subsidiaries.',
                    'domain' => 'apex.nff.dotnetbd.com',
                    'driver' => 'column_scoped',
                    'status' => 'Active',
                    'users' => 86,
                    'created_at' => '2026-02-15',
                ],
                [
                    'id' => 'tenant_sandbox',
                    'name' => 'SaaS Sandbox Org',
                    'site_title' => 'SaaS Testing & Sandbox',
                    'description' => 'Isolated staging environment for third-party integrations, webhook testing, and developer pilots.',
                    'domain' => 'sandbox.nff.dotnetbd.com',
                    'driver' => 'schema_scoped',
                    'status' => 'Trial',
                    'users' => 5,
                    'created_at' => '2026-03-01',
                ],
            ],
            'system_users', 'users' => [
                [
                    'id' => 'usr_001',
                    'name' => 'Md. Atiquzzaman Ashish',
                    'email' => 'ashish@dotnetbd.com',
                    'role' => 'super_admin',
                    'tenant_id' => 'tenant_primary',
                    'status' => 'Active',
                    'created_at' => '2026-01-01 10:00',
                ],
                [
                    'id' => 'usr_002',
                    'name' => 'Tanvir Rahman',
                    'email' => 'tanvir@corporate.com',
                    'role' => 'tenant_admin',
                    'tenant_id' => 'tenant_primary',
                    'status' => 'Active',
                    'created_at' => '2026-01-12 11:30',
                ],
                [
                    'id' => 'usr_003',
                    'name' => 'Nusrat Jahan',
                    'email' => 'nusrat@apex.com',
                    'role' => 'manager',
                    'tenant_id' => 'tenant_enterprise',
                    'status' => 'Active',
                    'created_at' => '2026-02-18 14:15',
                ],
                [
                    'id' => 'usr_004',
                    'name' => 'Kamal Hossain',
                    'email' => 'kamal@corporate.com',
                    'role' => 'staff',
                    'tenant_id' => 'tenant_primary',
                    'status' => 'Active',
                    'created_at' => '2026-03-02 09:45',
                ],
                [
                    'id' => 'usr_005',
                    'name' => 'Rafiqul Islam',
                    'email' => 'rafiq@client.com',
                    'role' => 'client',
                    'tenant_id' => 'tenant_sandbox',
                    'status' => 'Active',
                    'created_at' => '2026-03-10 16:20',
                ],
            ],
            default => [],
        };
    }
}
