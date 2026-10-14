<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\IMDG\Store;

use NetFusion\Core\Database\Connection;
use NetFusion\Core\IMDG\Contracts\Store\GridPersistenceStoreInterface;
use PDO;

class DatabaseGridStore implements GridPersistenceStoreInterface
{
    protected PDO $pdo;

    public function __construct(PDO|Connection $connection)
    {
        $this->pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;
    }

    public function load(string $mapName, string $key): mixed
    {
        $stmt = $this->pdo->prepare("
            SELECT entry_value, expires_at FROM nf_imdg_map_entries
            WHERE map_name = :map AND entry_key = :key
            LIMIT 1
        ");
        $stmt->execute([':map' => $mapName, ':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $now = microtime(true);
        $expiresAt = (float) $row['expires_at'];

        if ($expiresAt > 0.0 && $now > $expiresAt) {
            $this->delete($mapName, $key);
            return null;
        }

        $val = json_decode($row['entry_value'], true);
        return $val !== null ? $val : $row['entry_value'];
    }

    public function loadAll(string $mapName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT entry_key, entry_value, expires_at FROM nf_imdg_map_entries
            WHERE map_name = :map
        ");
        $stmt->execute([':map' => $mapName]);

        $now = microtime(true);
        $results = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $expiresAt = (float) $row['expires_at'];
            if ($expiresAt > 0.0 && $now > $expiresAt) {
                $this->delete($mapName, $row['entry_key']);
                continue;
            }

            $val = json_decode($row['entry_value'], true);
            $results[$row['entry_key']] = ($val !== null) ? $val : $row['entry_value'];
        }

        return $results;
    }

    public function store(string $mapName, string $key, mixed $value, int $ttlSeconds = 0): void
    {
        $now = microtime(true);
        $expiresAt = $ttlSeconds > 0 ? ($now + $ttlSeconds) : 0.0;
        $valJson = json_encode($value);

        $sql = "
            INSERT INTO nf_imdg_map_entries (map_name, entry_key, entry_value, expires_at, updated_at)
            VALUES (:map, :key, :val, :exp, :upd)
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':map' => $mapName,
                ':key' => $key,
                ':val' => $valJson,
                ':exp' => $expiresAt,
                ':upd' => $now,
            ]);
        } catch (\Throwable) {
            $updateSql = "
                UPDATE nf_imdg_map_entries SET
                    entry_value = :val,
                    expires_at = :exp,
                    updated_at = :upd
                WHERE map_name = :map AND entry_key = :key
            ";
            $stmt = $this->pdo->prepare($updateSql);
            $stmt->execute([
                ':map' => $mapName,
                ':key' => $key,
                ':val' => $valJson,
                ':exp' => $expiresAt,
                ':upd' => $now,
            ]);
        }
    }

    public function storeAll(string $mapName, array $entries): void
    {
        foreach ($entries as $key => $value) {
            $this->store($mapName, (string) $key, $value);
        }
    }

    public function delete(string $mapName, string $key): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM nf_imdg_map_entries WHERE map_name = :map AND entry_key = :key");
        $stmt->execute([':map' => $mapName, ':key' => $key]);
    }

    public function deleteAll(string $mapName): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM nf_imdg_map_entries WHERE map_name = :map");
        $stmt->execute([':map' => $mapName]);
    }
}
