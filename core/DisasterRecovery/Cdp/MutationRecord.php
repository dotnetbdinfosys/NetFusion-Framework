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

namespace NetFusion\Core\DisasterRecovery\Cdp;

use JsonSerializable;

/**
 * Class MutationRecord
 *
 * Immutable record of a database mutation with sub-millisecond timestamp
 * and cryptographic hash chaining for Zero-RPO Continuous Data Protection.
 */
class MutationRecord implements JsonSerializable
{
    public const OP_INSERT = 'INSERT';
    public const OP_UPDATE = 'UPDATE';
    public const OP_DELETE = 'DELETE';

    public function __construct(
        public readonly string $txId,
        public readonly string $table,
        public readonly string $operation,
        public readonly mixed $primaryKey,
        public readonly ?array $beforeState,
        public readonly ?array $afterState,
        public readonly float $timestamp,
        public readonly string $region = 'primary',
        public readonly ?string $previousChecksum = null,
        public ?string $checksum = null
    ) {
        $this->checksum = $this->checksum ?? $this->computeChecksum($this->previousChecksum);
    }

    public function computeChecksum(?string $prev = null): string
    {
        $payload = implode('|', [
            $prev ?? 'GENESIS',
            $this->txId,
            $this->table,
            $this->operation,
            json_encode($this->primaryKey),
            json_encode($this->beforeState),
            json_encode($this->afterState),
            sprintf('%.6f', $this->timestamp),
            $this->region,
        ]);

        return hash('sha256', $payload);
    }

    public function verifyIntegrity(?string $prev = null): bool
    {
        return hash_equals($this->checksum, $this->computeChecksum($prev));
    }

    public function toArray(): array
    {
        return [
            'tx_id'             => $this->txId,
            'table'             => $this->table,
            'operation'         => $this->operation,
            'primary_key'       => $this->primaryKey,
            'before_state'      => $this->beforeState,
            'after_state'       => $this->afterState,
            'timestamp'         => $this->timestamp,
            'region'            => $this->region,
            'previous_checksum' => $this->previousChecksum,
            'checksum'          => $this->checksum,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            txId: $data['tx_id'],
            table: $data['table'],
            operation: $data['operation'],
            primaryKey: $data['primary_key'],
            beforeState: $data['before_state'] ?? null,
            afterState: $data['after_state'] ?? null,
            timestamp: (float) $data['timestamp'],
            region: $data['region'] ?? 'primary',
            previousChecksum: $data['previous_checksum'] ?? null,
            checksum: $data['checksum'] ?? null
        );
    }
}
