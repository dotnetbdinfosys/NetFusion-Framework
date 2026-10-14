<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tracing/ApmManager.php
| Module    : Tracing
| Namespace : NetFusion\Core\Tracing
| Purpose   : Application Performance Monitoring (APM) engine tracking latencies, memory & queries.
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

namespace NetFusion\Core\Tracing;

/**
 * Class ApmManager
 *
 * Collects runtime performance telemetry including HTTP latency percentiles,
 * database query durations, memory peaks, and cache efficiency.
 *
 * @package NetFusion\Core\Tracing
 */
class ApmManager
{
    /**
     * Recorded HTTP requests.
     *
     * @var array<array{method: string, uri: string, status: int, duration_ms: float, memory_bytes: int, timestamp: float}>
     */
    protected array $requests = [];

    /**
     * Recorded database queries.
     *
     * @var array<array{sql: string, duration_ms: float, bindings: array, timestamp: float}>
     */
    protected array $queries = [];

    /**
     * Cache hits count.
     */
    protected int $cacheHits = 0;

    /**
     * Cache misses count.
     */
    protected int $cacheMisses = 0;

    /**
     * Recorded background queue jobs.
     *
     * @var array<array{job: string, duration_ms: float, success: bool}>
     */
    protected array $queueJobs = [];

    /**
     * Record an executed HTTP request.
     */
    public function recordRequest(
        string $method,
        string $uri,
        int $statusCode,
        float $durationMs,
        int $memoryBytes = 0
    ): void {
        $this->requests[] = [
            'method'       => strtoupper($method),
            'uri'          => $uri,
            'status'       => $statusCode,
            'duration_ms'  => $durationMs,
            'memory_bytes' => $memoryBytes > 0 ? $memoryBytes : memory_get_peak_usage(true),
            'timestamp'    => microtime(true),
        ];
    }

    /**
     * Record an executed database query.
     */
    public function recordQuery(string $sql, float $durationMs, array $bindings = []): void
    {
        $this->queries[] = [
            'sql'         => $sql,
            'duration_ms' => round($durationMs, 3),
            'bindings'    => $bindings,
            'timestamp'   => microtime(true),
        ];
    }

    /**
     * Record a cache hit.
     */
    public function recordCacheHit(string $key = ''): void
    {
        $this->cacheHits++;
    }

    /**
     * Record a cache miss.
     */
    public function recordCacheMiss(string $key = ''): void
    {
        $this->cacheMisses++;
    }

    /**
     * Record a processed queue job.
     */
    public function recordQueueJob(string $job, float $durationMs, bool $success = true): void
    {
        $this->queueJobs[] = [
            'job'         => $job,
            'duration_ms' => round($durationMs, 3),
            'success'     => $success,
        ];
    }

    /**
     * Retrieve queries that exceeded the slow query threshold in milliseconds.
     *
     * @param float $thresholdMs
     * @return array<array{sql: string, duration_ms: float, bindings: array, timestamp: float}>
     */
    public function getSlowQueries(float $thresholdMs = 50.0): array
    {
        $slow = array_filter($this->queries, fn(array $q) => $q['duration_ms'] >= $thresholdMs);

        // Sort descending by duration
        usort($slow, fn($a, $b) => $b['duration_ms'] <=> $a['duration_ms']);

        return array_values($slow);
    }

    /**
     * Get all recorded queries.
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Get all recorded requests.
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    /**
     * Calculate percentile value from an array of numbers.
     *
     * @param array<float|int> $values
     * @param float            $percentile (0.0 - 100.0)
     * @return float
     */
    public function calculatePercentile(array $values, float $percentile): float
    {
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $index = ($percentile / 100.0) * ($count - 1);
        $floor = (int) floor($index);
        $ceil = (int) ceil($index);

        if ($floor === $ceil) {
            return (float) $values[$floor];
        }

        $fraction = $index - $floor;
        return (float) ($values[$floor] + $fraction * ($values[$ceil] - $values[$floor]));
    }

    /**
     * Generate an aggregated APM telemetry summary report.
     *
     * @return array<string, mixed>
     */
    public function getMetricsSummary(): array
    {
        $requestCount = count($this->requests);
        $errorCount = 0;
        $latencies = [];

        foreach ($this->requests as $req) {
            $latencies[] = $req['duration_ms'];
            if ($req['status'] >= 400) {
                $errorCount++;
            }
        }

        $avgLatency = $requestCount > 0 ? array_sum($latencies) / $requestCount : 0.0;
        $p50 = $this->calculatePercentile($latencies, 50.0);
        $p95 = $this->calculatePercentile($latencies, 95.0);
        $p99 = $this->calculatePercentile($latencies, 99.0);

        // Queries analysis
        $queryCount = count($this->queries);
        $queryDurations = array_column($this->queries, 'duration_ms');
        $totalQueryTime = array_sum($queryDurations);
        $slowQueries = $this->getSlowQueries(50.0);

        // Cache analysis
        $totalCacheOps = $this->cacheHits + $this->cacheMisses;
        $hitRatio = $totalCacheOps > 0 ? round(($this->cacheHits / $totalCacheOps) * 100.0, 2) : 0.0;

        return [
            'requests' => [
                'total'        => $requestCount,
                'errors'       => $errorCount,
                'error_rate'   => $requestCount > 0 ? round(($errorCount / $requestCount) * 100.0, 2) : 0.0,
                'latency_avg'  => round($avgLatency, 2),
                'latency_p50'  => round($p50, 2),
                'latency_p95'  => round($p95, 2),
                'latency_p99'  => round($p99, 2),
            ],
            'database' => [
                'total_queries'  => $queryCount,
                'total_time_ms'  => round($totalQueryTime, 2),
                'avg_time_ms'    => $queryCount > 0 ? round($totalQueryTime / $queryCount, 2) : 0.0,
                'slow_queries'   => count($slowQueries),
            ],
            'cache' => [
                'hits'        => $this->cacheHits,
                'misses'      => $this->cacheMisses,
                'hit_ratio'   => $hitRatio,
            ],
            'memory' => [
                'peak_bytes' => memory_get_peak_usage(true),
                'peak_mb'    => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ],
            'queue' => [
                'total_jobs' => count($this->queueJobs),
                'success'    => count(array_filter($this->queueJobs, fn($j) => $j['success'])),
                'failed'     => count(array_filter($this->queueJobs, fn($j) => ! $j['success'])),
            ],
        ];
    }

    /**
     * Clear all recorded performance metrics.
     */
    public function reset(): void
    {
        $this->requests = [];
        $this->queries = [];
        $this->cacheHits = 0;
        $this->cacheMisses = 0;
        $this->queueJobs = [];
    }
}
