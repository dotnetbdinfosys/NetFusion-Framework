<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Etcd\Models;

/**
 * Represents an immutable point-in-time revision of a key in the MVCC store.
 */
class KeyRevision
{
    public function __construct(
        public readonly string $key,
        public readonly int $createRevision,
        public readonly int $modRevision,
        public readonly int $version,
        public readonly ?string $value,
        public readonly int $leaseId = 0,
        public readonly bool $isTombstone = false
    ) {
    }

    /**
     * Check if this revision represents a deleted/tombstone key.
     */
    public function isDeleted(): bool
    {
        return $this->isTombstone;
    }

    /**
     * Convert the key revision to an array representation.
     */
    public function toArray(): array
    {
        return [
            'key'            => $this->key,
            'createRevision' => $this->createRevision,
            'modRevision'    => $this->modRevision,
            'version'        => $this->version,
            'value'          => $this->value,
            'leaseId'        => $this->leaseId,
            'isTombstone'    => $this->isTombstone,
        ];
    }

    /**
     * Create a KeyRevision from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: (string) ($data['key'] ?? ''),
            createRevision: (int) ($data['createRevision'] ?? 0),
            modRevision: (int) ($data['modRevision'] ?? 0),
            version: (int) ($data['version'] ?? 1),
            value: isset($data['value']) ? (string) $data['value'] : null,
            leaseId: (int) ($data['leaseId'] ?? 0),
            isTombstone: (bool) ($data['isTombstone'] ?? false)
        );
    }
}
