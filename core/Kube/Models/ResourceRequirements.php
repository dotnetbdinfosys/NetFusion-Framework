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

namespace NetFusion\Core\Kube\Models;

/**
 * Encapsulates CPU (in millicores) and Memory (in MiB) requests and limits.
 */
class ResourceRequirements
{
    /**
     * @param int $cpuMillis CPU in millicores (e.g., 500m = 500, 1 core = 1000)
     * @param int $memoryMib Memory in MiB (e.g., 256Mi = 256, 1Gi = 1024)
     */
    public function __construct(
        protected int $cpuMillis = 0,
        protected int $memoryMib = 0
    ) {
    }

    /**
     * Parse Kubernetes resource strings (e.g., '500m', '1', '256Mi', '1Gi').
     */
    public static function parse(string|int $cpu, string|int $memory): self
    {
        return new self(
            self::parseCpu($cpu),
            self::parseMemory($memory)
        );
    }

    /**
     * Parse CPU specification to millicores.
     */
    public static function parseCpu(string|int $cpu): int
    {
        if (is_int($cpu)) {
            return $cpu;
        }

        $cpu = trim($cpu);
        if (str_ends_with($cpu, 'm')) {
            return (int) substr($cpu, 0, -1);
        }

        return (int) (((float) $cpu) * 1000);
    }

    /**
     * Parse Memory specification to MiB.
     */
    public static function parseMemory(string|int $mem): int
    {
        if (is_int($mem)) {
            return $mem;
        }

        $mem = trim($mem);
        if (str_ends_with($mem, 'Gi')) {
            return (int) substr($mem, 0, -2) * 1024;
        }
        if (str_ends_with($mem, 'Mi')) {
            return (int) substr($mem, 0, -2);
        }
        if (str_ends_with($mem, 'Ki')) {
            return (int) ceil(((int) substr($mem, 0, -2)) / 1024);
        }
        if (str_ends_with($mem, 'G')) {
            return (int) substr($mem, 0, -1) * 1000;
        }
        if (str_ends_with($mem, 'M')) {
            return (int) substr($mem, 0, -1);
        }

        // Default assume MiB or bytes
        $val = (int) $mem;
        return $val > 100000 ? (int) ($val / (1024 * 1024)) : $val;
    }

    public function getCpuMillis(): int
    {
        return $this->cpuMillis;
    }

    public function getMemoryMib(): int
    {
        return $this->memoryMib;
    }

    /**
     * Check if this resource has enough capacity to satisfy another requirement.
     */
    public function fits(self $needed): bool
    {
        return $this->cpuMillis >= $needed->getCpuMillis()
            && $this->memoryMib >= $needed->getMemoryMib();
    }

    /**
     * Add resources and return a new instance.
     */
    public function add(self $other): self
    {
        return new self(
            $this->cpuMillis + $other->getCpuMillis(),
            $this->memoryMib + $other->getMemoryMib()
        );
    }

    /**
     * Subtract resources and return a new instance.
     */
    public function subtract(self $other): self
    {
        return new self(
            max(0, $this->cpuMillis - $other->getCpuMillis()),
            max(0, $this->memoryMib - $other->getMemoryMib())
        );
    }

    /**
     * Export as array representation.
     *
     * @return array{cpu: string, memory: string, cpu_millis: int, memory_mib: int}
     */
    public function toArray(): array
    {
        return [
            'cpu'         => "{$this->cpuMillis}m",
            'memory'      => "{$this->memoryMib}Mi",
            'cpu_millis'  => $this->cpuMillis,
            'memory_mib'  => $this->memoryMib,
        ];
    }
}
