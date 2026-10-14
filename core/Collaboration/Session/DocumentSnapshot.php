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

namespace NetFusion\Core\Collaboration\Session;

/**
 * Class DocumentSnapshot
 *
 * Immutable, cryptographically verified snapshot of a collaborative document's full state.
 */
class DocumentSnapshot
{
    public readonly string $checksum;

    public function __construct(
        public readonly string $docId,
        public readonly int $revision,
        public readonly string $text,
        public readonly array $metadata = [],
        public readonly array $crdtState = [],
        public readonly float $timestamp = 0.0,
        ?string $checksum = null
    ) {
        $this->checksum = $checksum ?? $this->computeChecksum();
    }

    public function computeChecksum(): string
    {
        return hash('sha256', json_encode([
            'docId' => $this->docId,
            'revision' => $this->revision,
            'text' => $this->text,
            'metadata' => $this->metadata,
        ], JSON_UNESCAPED_SLASHES));
    }

    public function verifyChecksum(): bool
    {
        return hash_equals($this->checksum, $this->computeChecksum());
    }

    public function toArray(): array
    {
        return [
            'docId' => $this->docId,
            'revision' => $this->revision,
            'text' => $this->text,
            'metadata' => $this->metadata,
            'crdtState' => $this->crdtState,
            'timestamp' => $this->timestamp > 0.0 ? $this->timestamp : microtime(true),
            'checksum' => $this->checksum,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['docId'] ?? ''),
            (int) ($data['revision'] ?? 0),
            (string) ($data['text'] ?? ''),
            (array) ($data['metadata'] ?? []),
            (array) ($data['crdtState'] ?? []),
            (float) ($data['timestamp'] ?? microtime(true)),
            isset($data['checksum']) ? (string) $data['checksum'] : null
        );
    }
}
