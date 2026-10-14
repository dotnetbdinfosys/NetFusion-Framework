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

namespace NetFusion\Core\Security\Vault\Store;

use PDO;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\Security\Vault\Contracts\VaultStoreInterface;
use NetFusion\Core\Security\Vault\Encryption\EncryptedPayload;
use NetFusion\Core\Security\Vault\Secret;
use NetFusion\Core\Security\Vault\SecretLease;

class DatabaseVaultStore implements VaultStoreInterface
{
    protected PDO $pdo;
    protected string $secretsTable;
    protected string $leasesTable;

    public function __construct(
        PDO|Connection $connection,
        string $secretsTable = 'netfusion_vault_secrets',
        string $leasesTable = 'netfusion_vault_leases'
    ) {
        $this->pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;
        $this->secretsTable = $secretsTable;
        $this->leasesTable = $leasesTable;
    }

    public function get(string $path, ?int $version = null): ?Secret
    {
        if ($version !== null) {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->secretsTable} WHERE path = :path AND version = :version LIMIT 1");
            $stmt->execute(['path' => $path, 'version' => $version]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->secretsTable} WHERE path = :path ORDER BY version DESC LIMIT 1");
            $stmt->execute(['path' => $path]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->hydrateSecret($row);
    }

    public function set(Secret $secret): bool
    {
        $id = bin2hex(random_bytes(16));
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("INSERT INTO {$this->secretsTable} (
            id, path, version, encrypted_payload, metadata_json, created_at, updated_at
        ) VALUES (
            :id, :path, :version, :encrypted_payload, :metadata_json, :created_at, :updated_at
        )");

        return $stmt->execute([
            'id' => $id,
            'path' => $secret->path,
            'version' => $secret->version,
            'encrypted_payload' => $secret->payload->toJson(),
            'metadata_json' => json_encode($secret->metadata),
            'created_at' => $secret->createdAt ?? $now,
            'updated_at' => $now,
        ]);
    }

    public function delete(string $path, ?int $version = null): bool
    {
        if ($version !== null) {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->secretsTable} WHERE path = :path AND version = :version");
            return $stmt->execute(['path' => $path, 'version' => $version]);
        }

        $stmt = $this->pdo->prepare("DELETE FROM {$this->secretsTable} WHERE path = :path");
        return $stmt->execute(['path' => $path]);
    }

    public function list(string $prefix = ''): array
    {
        if (empty($prefix)) {
            $stmt = $this->pdo->query("SELECT DISTINCT path FROM {$this->secretsTable} ORDER BY path ASC");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        }

        $stmt = $this->pdo->prepare("SELECT DISTINCT path FROM {$this->secretsTable} WHERE path LIKE :prefix ORDER BY path ASC");
        $stmt->execute(['prefix' => $prefix . '%']);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function all(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->secretsTable} ORDER BY path ASC, version ASC");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $secrets = [];
        foreach ($rows as $row) {
            $secrets[] = $this->hydrateSecret($row);
        }
        return $secrets;
    }

    public function saveLease(SecretLease $lease): bool
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("INSERT INTO {$this->leasesTable} (
            id, path, encrypted_credentials, expires_at, ttl, renewable, renew_count, revoked, created_at
        ) VALUES (
            :id, :path, :encrypted_credentials, :expires_at, :ttl, :renewable, :renew_count, :revoked, :created_at
        )");

        return $stmt->execute([
            'id' => $lease->id,
            'path' => $lease->path,
            'encrypted_credentials' => $lease->encryptedCredentials->toJson(),
            'expires_at' => $lease->expiresAt,
            'ttl' => $lease->ttl,
            'renewable' => $lease->renewable ? 1 : 0,
            'renew_count' => $lease->renewCount,
            'revoked' => $lease->revoked ? 1 : 0,
            'created_at' => $lease->createdAt ?? $now,
        ]);
    }

    public function getLease(string $leaseId): ?SecretLease
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->leasesTable} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $leaseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new SecretLease(
            id: $row['id'],
            path: $row['path'],
            encryptedCredentials: EncryptedPayload::fromJson($row['encrypted_credentials']),
            expiresAt: (float)$row['expires_at'],
            ttl: (int)$row['ttl'],
            renewable: (bool)$row['renewable'],
            renewCount: (int)$row['renew_count'],
            revoked: (bool)$row['revoked'],
            createdAt: $row['created_at']
        );
    }

    public function revokeLease(string $leaseId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->leasesTable} SET revoked = 1 WHERE id = :id");
        return $stmt->execute(['id' => $leaseId]);
    }

    public function pruneExpiredLeases(): int
    {
        $now = microtime(true);
        $stmt = $this->pdo->prepare("DELETE FROM {$this->leasesTable} WHERE expires_at < :ts OR revoked = 1");
        $stmt->execute(['ts' => $now]);
        return $stmt->rowCount();
    }

    protected function hydrateSecret(array $row): Secret
    {
        return new Secret(
            path: $row['path'],
            version: (int)$row['version'],
            payload: EncryptedPayload::fromJson($row['encrypted_payload']),
            metadata: json_decode($row['metadata_json'] ?? '{}', true) ?: [],
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at']
        );
    }
}
