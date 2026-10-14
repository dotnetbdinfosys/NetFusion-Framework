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

namespace NetFusion\Core\Auth\WebAuthn\Store;

use PDO;
use NetFusion\Core\Auth\WebAuthn\Contracts\CredentialStoreInterface;
use NetFusion\Core\Auth\WebAuthn\Data\WebAuthnCredential;
use NetFusion\Core\Database\Connection;

class DatabaseCredentialStore implements CredentialStoreInterface
{
    protected PDO $pdo;
    protected string $table;

    public function __construct(PDO|Connection $connection, string $table = 'netfusion_webauthn_credentials')
    {
        $this->pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;
        $this->table = $table;
    }

    public function findById(string $credentialId): ?WebAuthnCredential
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $credentialId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByUserId(string|int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->execute(['user_id' => (string)$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $list = [];
        foreach ($rows as $row) {
            $list[] = $this->hydrate($row);
        }
        return $list;
    }

    public function save(WebAuthnCredential $credential): bool
    {
        $sql = "INSERT INTO {$this->table} (
            id, user_id, name, public_key_pem, attestation_type, aaguid, sign_count, cose_algorithm, transports, created_at, last_used_at
        ) VALUES (
            :id, :user_id, :name, :public_key_pem, :attestation_type, :aaguid, :sign_count, :cose_algorithm, :transports, :created_at, :last_used_at
        )";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $credential->id,
            'user_id' => (string)$credential->userId,
            'name' => $credential->name,
            'public_key_pem' => $credential->publicKeyPem,
            'attestation_type' => $credential->attestationType,
            'aaguid' => $credential->aaguid,
            'sign_count' => $credential->signCount,
            'cose_algorithm' => $credential->coseAlgorithm,
            'transports' => !empty($credential->transports) ? json_encode($credential->transports) : null,
            'created_at' => $credential->createdAt ?? date('Y-m-d H:i:s'),
            'last_used_at' => $credential->lastUsedAt,
        ]);
    }

    public function updateCounter(string $credentialId, int $newCounter): bool
    {
        $sql = "UPDATE {$this->table} SET sign_count = :sign_count, last_used_at = :last_used_at WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'sign_count' => $newCounter,
            'last_used_at' => date('Y-m-d H:i:s'),
            'id' => $credentialId,
        ]);
    }

    public function delete(string $credentialId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $credentialId]);
    }

    protected function hydrate(array $row): WebAuthnCredential
    {
        $transports = [];
        if (!empty($row['transports'])) {
            $transports = is_array($row['transports']) ? $row['transports'] : (json_decode($row['transports'], true) ?: []);
        }

        return new WebAuthnCredential(
            id: (string)$row['id'],
            userId: $row['user_id'],
            publicKeyPem: (string)$row['public_key_pem'],
            attestationType: (string)$row['attestation_type'],
            aaguid: (string)($row['aaguid'] ?? ''),
            signCount: (int)$row['sign_count'],
            coseAlgorithm: isset($row['cose_algorithm']) ? (string)$row['cose_algorithm'] : null,
            name: isset($row['name']) ? (string)$row['name'] : null,
            createdAt: isset($row['created_at']) ? (string)$row['created_at'] : null,
            lastUsedAt: isset($row['last_used_at']) ? (string)$row['last_used_at'] : null,
            transports: $transports
        );
    }
}
