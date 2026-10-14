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

namespace NetFusion\Core\Security\Vault;

use JsonSerializable;
use NetFusion\Core\Security\Vault\Encryption\EncryptedPayload;

class SecretLease implements JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $path,
        public readonly EncryptedPayload $encryptedCredentials,
        public float $expiresAt,
        public int $ttl,
        public bool $renewable = true,
        public int $renewCount = 0,
        public bool $revoked = false,
        public ?string $createdAt = null
    ) {
        $this->createdAt = $createdAt ?? date('Y-m-d H:i:s');
    }

    public function isExpired(): bool
    {
        return $this->revoked || microtime(true) >= $this->expiresAt;
    }

    public function getRemainingSeconds(): int
    {
        if ($this->revoked) {
            return 0;
        }
        return max(0, (int)ceil($this->expiresAt - microtime(true)));
    }

    public function renew(int $incrementSeconds): self
    {
        if (!$this->renewable || $this->revoked) {
            throw new \RuntimeException("Cannot renew lease '{$this->id}': lease is non-renewable or revoked.");
        }

        $this->expiresAt = microtime(true) + $incrementSeconds;
        $this->ttl = $incrementSeconds;
        $this->renewCount++;
        return $this;
    }

    public function revoke(): self
    {
        $this->revoked = true;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'encrypted_credentials' => $this->encryptedCredentials->toArray(),
            'expires_at' => $this->expiresAt,
            'ttl' => $this->ttl,
            'remaining_seconds' => $this->getRemainingSeconds(),
            'renewable' => $this->renewable,
            'renew_count' => $this->renewCount,
            'revoked' => $this->revoked,
            'created_at' => $this->createdAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)$data['id'],
            path: (string)$data['path'],
            encryptedCredentials: EncryptedPayload::fromArray((array)$data['encrypted_credentials']),
            expiresAt: (float)$data['expires_at'],
            ttl: (int)$data['ttl'],
            renewable: (bool)($data['renewable'] ?? true),
            renewCount: (int)($data['renew_count'] ?? 0),
            revoked: (bool)($data['revoked'] ?? false),
            createdAt: isset($data['created_at']) ? (string)$data['created_at'] : null
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
