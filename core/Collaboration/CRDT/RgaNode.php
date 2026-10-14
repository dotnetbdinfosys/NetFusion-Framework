<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\Collaboration\CRDT;

/**
 * Class RgaNode
 *
 * Represents an individual atomic node (character or segment) within an RGA CRDT sequence.
 */
class RgaNode
{
    public function __construct(
        public readonly CharId $id,
        public readonly ?CharId $prevId,
        public readonly string $value,
        public bool $deleted = false,
        public readonly float $timestamp = 0.0
    ) {
    }

    public function markDeleted(): void
    {
        $this->deleted = true;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toArray(),
            'prevId' => $this->prevId?->toArray(),
            'value' => $this->value,
            'deleted' => $this->deleted,
            'timestamp' => $this->timestamp,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            CharId::fromArray($data['id']),
            isset($data['prevId']) && $data['prevId'] !== null ? CharId::fromArray($data['prevId']) : null,
            (string) ($data['value'] ?? ''),
            (bool) ($data['deleted'] ?? false),
            (float) ($data['timestamp'] ?? 0.0)
        );
    }
}
