<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Enclave/Runtime/EnclaveContext.php
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

namespace NetFusion\Core\Security\Enclave\Runtime;

use NetFusion\Core\Security\Enclave\Attestation\PlatformMeasurement;
use NetFusion\Core\Security\Enclave\Enums\EnclaveType;

/**
 * Isolated Hardware Enclave Execution Environment & Memory Boundary.
 */
class EnclaveContext
{
    private array $isolatedMemory = [];

    public function __construct(
        private string $enclaveId,
        private EnclaveType $enclaveType,
        private PlatformMeasurement $measurement,
        private int $memoryLimitMb = 128
    ) {
    }

    public function getEnclaveId(): string
    {
        return $this->enclaveId;
    }

    public function getEnclaveType(): EnclaveType
    {
        return $this->enclaveType;
    }

    public function getMeasurement(): PlatformMeasurement
    {
        return $this->measurement;
    }

    public function getMemoryLimitMb(): int
    {
        return $this->memoryLimitMb;
    }

    /**
     * Execute a closure strictly inside the enclave isolated context.
     */
    public function runIsolated(callable $computation): mixed
    {
        return $computation($this);
    }

    public function set(string $key, mixed $value): self
    {
        $this->isolatedMemory[$key] = $value;
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->isolatedMemory[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->isolatedMemory);
    }

    public function clear(): void
    {
        $this->isolatedMemory = [];
    }

    public function toArray(): array
    {
        return [
            'enclave_id'      => $this->enclaveId,
            'enclave_type'    => $this->enclaveType->value,
            'measurement'     => $this->measurement->toArray(),
            'memory_limit_mb' => $this->memoryLimitMb,
            'items_count'     => count($this->isolatedMemory),
        ];
    }
}
