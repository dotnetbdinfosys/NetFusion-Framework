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

class Secret implements JsonSerializable
{
    public function __construct(
        public readonly string $path,
        public readonly int $version,
        public readonly EncryptedPayload $payload,
        public readonly array $metadata = [],
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'version' => $this->version,
            'payload' => $this->payload->toArray(),
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt ?? date('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt ?? date('Y-m-d H:i:s'),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            path: (string)$data['path'],
            version: (int)($data['version'] ?? 1),
            payload: EncryptedPayload::fromArray((array)$data['payload']),
            metadata: (array)($data['metadata'] ?? []),
            createdAt: isset($data['created_at']) ? (string)$data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string)$data['updated_at'] : null
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
