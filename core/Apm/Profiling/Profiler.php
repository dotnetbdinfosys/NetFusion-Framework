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

namespace NetFusion\Core\Apm\Profiling;

/**
 * Class Profiler
 *
 * Wall-clock and memory profiler with named checkpoints.
 *
 * Usage:
 *   $profiler = new Profiler('bootstrap');
 *   $profiler->checkpoint('config_loaded');
 *   ... do work ...
 *   $profiler->checkpoint('routes_registered');
 *   $profiler->stop();
 *   print_r($profiler->getSummary());
 */
class Profiler
{
    private float  $startTime;
    private int    $startMemory;
    private ?float $endTime   = null;
    private int    $endMemory = 0;

    /** @var array[] [name, elapsed_ms, memory_bytes] */
    private array $checkpoints = [];

    /** @var array{name:string, ms:float, memory:int}|null Slowest checkpoint */
    private ?array $slowestCheckpoint = null;
    private float  $maxElapsed        = 0.0;

    public function __construct(
        private readonly string $name = 'default'
    ) {
        $this->startTime   = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    // ------------------------------------------------------------------
    // Recording
    // ------------------------------------------------------------------

    public function checkpoint(string $label): self
    {
        $elapsedMs = $this->elapsedMs();
        $memory    = memory_get_usage(true);

        $cp = [
            'name'       => $label,
            'elapsed_ms' => $elapsedMs,
            'memory'     => $memory,
            'memory_mb'  => round($memory / 1024 / 1024, 2),
        ];
        $this->checkpoints[] = $cp;

        if ($elapsedMs > $this->maxElapsed) {
            $this->maxElapsed        = $elapsedMs;
            $this->slowestCheckpoint = $cp;
        }

        return $this;
    }

    public function stop(): self
    {
        $this->endTime   = microtime(true);
        $this->endMemory = memory_get_peak_usage(true);
        return $this;
    }

    // ------------------------------------------------------------------
    // Reporting
    // ------------------------------------------------------------------

    public function elapsedMs(): float
    {
        $end = $this->endTime ?? microtime(true);
        return round(($end - $this->startTime) * 1000, 3);
    }

    public function peakMemoryMb(): float
    {
        $peak = $this->endMemory ?: memory_get_peak_usage(true);
        return round($peak / 1024 / 1024, 2);
    }

    public function getCheckpoints(): array { return $this->checkpoints; }

    public function getSlowest(): ?array { return $this->slowestCheckpoint; }

    public function getSummary(): array
    {
        return [
            'name'          => $this->name,
            'total_ms'      => $this->elapsedMs(),
            'peak_memory_mb'=> $this->peakMemoryMb(),
            'checkpoints'   => $this->checkpoints,
            'slowest'       => $this->slowestCheckpoint,
        ];
    }

    public function render(): string
    {
        $summary = $this->getSummary();
        $out     = "\nProfiler: {$summary['name']}\n";
        $out    .= "Total: {$summary['total_ms']} ms | Peak Memory: {$summary['peak_memory_mb']} MB\n";
        $out    .= str_repeat('-', 60) . "\n";
        foreach ($summary['checkpoints'] as $cp) {
            $out .= sprintf("  %-30s  %8.3f ms  %6.2f MB\n", $cp['name'], $cp['elapsed_ms'], $cp['memory_mb']);
        }
        return $out;
    }
}
