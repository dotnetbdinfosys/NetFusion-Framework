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
 * Class SlowQueryDetector
 *
 * Records SQL queries with execution times and flags slow queries
 * that exceed a configurable threshold.
 */
class SlowQueryDetector
{
    /** @var array[] All recorded queries */
    private array $queries = [];
    private array $slow    = [];

    public function __construct(
        private readonly float $thresholdMs = 100.0
    ) {}

    // ------------------------------------------------------------------
    // Recording
    // ------------------------------------------------------------------

    public function record(string $sql, float $executionMs, array $bindings = []): void
    {
        $entry = [
            'sql'          => $sql,
            'execution_ms' => round($executionMs, 3),
            'bindings'     => $bindings,
            'slow'         => $executionMs >= $this->thresholdMs,
            'recorded_at'  => date('Y-m-d H:i:s'),
        ];

        $this->queries[] = $entry;

        if ($entry['slow']) {
            $this->slow[] = $entry;
        }
    }

    // ------------------------------------------------------------------
    // Reporting
    // ------------------------------------------------------------------

    public function getAll(): array      { return $this->queries; }
    public function getSlow(): array     { return $this->slow; }
    public function hasSlow(): bool      { return !empty($this->slow); }
    public function getCount(): int      { return count($this->queries); }
    public function getSlowCount(): int  { return count($this->slow); }
    public function getThreshold(): float { return $this->thresholdMs; }

    public function getAverageMs(): float
    {
        if (empty($this->queries)) return 0.0;
        return round(
            array_sum(array_column($this->queries, 'execution_ms')) / count($this->queries),
            3
        );
    }

    public function clear(): void { $this->queries = []; $this->slow = []; }

    public function toArray(): array
    {
        return [
            'total_queries'  => $this->getCount(),
            'slow_queries'   => $this->getSlowCount(),
            'threshold_ms'   => $this->thresholdMs,
            'average_ms'     => $this->getAverageMs(),
            'slow'           => $this->slow,
        ];
    }
}
