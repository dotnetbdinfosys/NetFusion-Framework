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

namespace NetFusion\Core\Security\Vault\Encryption;

use JsonSerializable;

class EncryptedPayload implements JsonSerializable
{
    public function __construct(
        public readonly int $version,
        public readonly string $cipher,
        public readonly string $iv,
        public readonly string $tag,
        public readonly string $edek,
        public readonly string $edekIv,
        public readonly string $edekTag,
        public readonly string $ciphertext,
        public readonly float $timestamp = 0.0
    ) {
    }

    public function toArray(): array
    {
        return [
            'v' => $this->version,
            'cipher' => $this->cipher,
            'iv' => $this->iv,
            'tag' => $this->tag,
            'edek' => $this->edek,
            'edek_iv' => $this->edekIv,
            'edek_tag' => $this->edekTag,
            'ciphertext' => $this->ciphertext,
            'ts' => $this->timestamp > 0.0 ? $this->timestamp : microtime(true),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            version: (int)($data['v'] ?? $data['version'] ?? 1),
            cipher: (string)($data['cipher'] ?? 'aes-256-gcm'),
            iv: (string)($data['iv'] ?? ''),
            tag: (string)($data['tag'] ?? ''),
            edek: (string)($data['edek'] ?? ''),
            edekIv: (string)($data['edek_iv'] ?? ''),
            edekTag: (string)($data['edek_tag'] ?? ''),
            ciphertext: (string)($data['ciphertext'] ?? ''),
            timestamp: (float)($data['ts'] ?? $data['timestamp'] ?? microtime(true))
        );
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES);
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid encrypted payload JSON string.');
        }
        return self::fromArray($data);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
