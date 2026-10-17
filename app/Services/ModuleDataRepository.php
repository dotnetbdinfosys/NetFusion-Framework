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
            } catch (Throwable $e) {
                // fallback to environment variables
            }
        }

        // Direct PDO fallback using environment variables
        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
        $database = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'netfusion_db';
        $username = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root';
        $password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
        $charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        self::$pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$pdo;
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

        // Ensure audit table exists first
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
                `created_at` DATETIME NOT NULL COMMENT 'Timestamp of deletion',
                INDEX `idx_entity` (`entity`),
                INDEX `idx_record_id` (`record_id`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            self::$verifiedTables['audit_deleted_records'] = true;
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

        // 3. Store footprint in audit_deleted_records table
        try {
            $auditStmt = $pdo->prepare("INSERT INTO `audit_deleted_records` 
                (`entity`, `record_id`, `record_data`, `deleted_by`, `ip_address`, `user_agent`, `request_uri`, `request_method`, `created_at`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $auditStmt->execute([
                $entity,
                $recordId,
                $recordJson,
                $deletedBy,
                $ipAddress,
                $userAgent,
                $requestUri,
                $requestMethod,
                date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            error_log("Failed to write audit footprint: " . $e->getMessage());
        }

        // 4. Perform the deletion from the entity table
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
    }

    /**
     * Restore a deleted record from the audit footprint back to its original table.
     */
    public static function restoreDeletedRecord(int $auditId): bool
    {
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
        $pdo = self::getPdo();
        $stmt = $pdo->query("DESCRIBE `{$table}`");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
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
                `role` VARCHAR(50) NOT NULL,
                `tenant_id` VARCHAR(100) NULL,
                `status` VARCHAR(50) DEFAULT 'Active',
                `created_at` VARCHAR(50) NULL,
                `updated_at` DATETIME NULL
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
