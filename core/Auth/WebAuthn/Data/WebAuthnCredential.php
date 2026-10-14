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

namespace NetFusion\Core\Auth\WebAuthn\Data;

use JsonSerializable;

class WebAuthnCredential implements JsonSerializable
{
    public function __construct(
        public readonly string $id,                     // Base64url credential ID
        public readonly string|int $userId,            // User ID
        public readonly string $publicKeyPem,          // Public Key in standard PEM format
        public readonly string $attestationType,       // e.g. 'none', 'packed', 'fido-u2f'
        public readonly string $aaguid,                // Authenticator AAGUID (hex or uuid)
        public int $signCount,                         // Monotonic counter
        public readonly ?string $coseAlgorithm = null, // e.g. 'ES256', 'RS256'
        public readonly ?string $name = null,          // User friendly label e.g. 'MacBook Touch ID'
        public readonly ?string $createdAt = null,
        public readonly ?string $lastUsedAt = null,
        public readonly array $transports = []         // ['internal', 'usb', 'nfc', 'ble', 'hybrid']
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'public_key_pem' => $this->publicKeyPem,
            'attestation_type' => $this->attestationType,
            'aaguid' => $this->aaguid,
            'sign_count' => $this->signCount,
            'cose_algorithm' => $this->coseAlgorithm,
            'name' => $this->name,
            'transports' => $this->transports,
            'created_at' => $this->createdAt,
            'last_used_at' => $this->lastUsedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
