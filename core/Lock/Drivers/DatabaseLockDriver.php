<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Lock/Drivers/DatabaseLockDriver.php
| Module    : Lock
| Namespace : NetFusion\Core\Lock\Drivers
| Purpose   : High-concurrency Database-backed distributed lock driver.
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

namespace NetFusion\Core\Lock\Drivers;

use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Lock\Contracts\LockDriverInterface;
use PDO;

class DatabaseLockDriver implements LockDriverInterface
{
    public function __construct(
        protected DatabaseManager $db,
        protected string $table = 'netfusion_locks'
    ) {
    }

    public function acquire(string $name, string $owner, int $seconds): bool
    {
        $pdo = $this->db->connection()->getPdo();
        $now = time();
        $expiration = $seconds !== 0 ? $now + $seconds : $now + 86400 * 365;
        $nowFormatted = date('Y-m-d H:i:s');

        // Check if existing lock is owned by current owner or expired
        $stmt = $pdo->prepare("SELECT owner, expiration FROM {$this->table} WHERE key = ?");
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // No lock exists, attempt atomic insert
            try {
                $insert = $pdo->prepare(
                    "INSERT INTO {$this->table} (key, owner, expiration, created_at, updated_at) VALUES (?, ?, ?, ?, ?)"
                );
                return $insert->execute([$name, $owner, $expiration, $nowFormatted, $nowFormatted]);
            } catch (\Throwable $e) {
                return false;
            }
        }

        // Lock exists. If expired or owner matches, update
        if ((int)$row['expiration'] <= $now || (string)$row['owner'] === $owner) {
            $update = $pdo->prepare(
                "UPDATE {$this->table} SET owner = ?, expiration = ?, updated_at = ? WHERE key = ? AND (expiration <= ? OR owner = ?)"
            );
            $update->execute([$owner, $expiration, $nowFormatted, $name, $now, $owner]);
            return $update->rowCount() > 0;
        }

        return false;
    }

    public function release(string $name, string $owner): bool
    {
        $pdo = $this->db->connection()->getPdo();
        $stmt = $pdo->prepare("DELETE FROM {$this->table} WHERE key = ? AND owner = ?");
        $stmt->execute([$name, $owner]);
        return $stmt->rowCount() > 0;
    }

    public function forceRelease(string $name): void
    {
        $pdo = $this->db->connection()->getPdo();
        $stmt = $pdo->prepare("DELETE FROM {$this->table} WHERE key = ?");
        $stmt->execute([$name]);
    }

    public function getCurrentOwner(string $name): ?string
    {
        $pdo = $this->db->connection()->getPdo();
        $stmt = $pdo->prepare("SELECT owner, expiration FROM {$this->table} WHERE key = ?");
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        if ((int)$row['expiration'] <= time()) {
            return null;
        }

        return (string)$row['owner'];
    }

    public function isLocked(string $name): bool
    {
        return $this->getCurrentOwner($name) !== null;
    }

    public function all(): array
    {
        $pdo = $this->db->connection()->getPdo();
        $stmt = $pdo->query("SELECT * FROM {$this->table} ORDER BY expiration DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cleanExpired(): int
    {
        $pdo = $this->db->connection()->getPdo();
        $stmt = $pdo->prepare("DELETE FROM {$this->table} WHERE expiration <= ?");
        $stmt->execute([time()]);
        return $stmt->rowCount();
    }
}