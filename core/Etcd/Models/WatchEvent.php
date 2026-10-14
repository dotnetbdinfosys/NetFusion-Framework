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

use NetFusion\Core\Etcd\Enums\EventType;

/**
 * Represents a streaming event observed on a watched key or prefix.
 */
class WatchEvent
{
    public function __construct(
        public readonly EventType $type,
        public readonly ?KeyRevision $kv,
        public readonly ?KeyRevision $prevKv = null,
        public readonly int $compactRevision = 0
    ) {
    }

    /**
     * Check if this is a PUT event.
     */
    public function isPut(): bool
    {
        return $this->type === EventType::PUT;
    }

    /**
     * Check if this is a DELETE event.
     */
    public function isDelete(): bool
    {
        return $this->type === EventType::DELETE;
    }

    /**
     * Get revision associated with the event.
     */
    public function getRevision(): int
    {
        return $this->kv?->modRevision ?? $this->prevKv?->modRevision ?? 0;
    }

    /**
     * Convert event to array representation.
     */
    public function toArray(): array
    {
        return [
            'type'            => $this->type->value,
            'kv'              => $this->kv?->toArray(),
            'prevKv'          => $this->prevKv?->toArray(),
            'compactRevision' => $this->compactRevision,
            'revision'        => $this->getRevision(),
        ];
    }
}
