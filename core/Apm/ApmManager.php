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

namespace NetFusion\Core\Apm;

use NetFusion\Core\Apm\Logging\StructuredLogger;
use NetFusion\Core\Apm\Tracing\Tracer;
use NetFusion\Core\Apm\Tracing\Span;
use NetFusion\Core\Apm\Profiling\Profiler;
use NetFusion\Core\Apm\Profiling\SlowQueryDetector;

/**
 * Class ApmManager
 *
 * Central facade for the Application Performance Monitoring engine.
 *
 * Provides:
 *   - Structured JSON logging (StructuredLogger)
 *   - Distributed tracing (Tracer + Span)
 *   - Performance profiling (Profiler)
 *   - Slow query detection (SlowQueryDetector)
 *
 * Usage:
 *   $apm = ApmManager::create();
 *   $apm->logger()->info('App started');
 *   $apm->tracer()->trace('db.query', function(Span $s) { ... });
 */
class ApmManager
{
    private StructuredLogger $logger;
    private Tracer           $tracer;
    private SlowQueryDetector $slowQueryDetector;

    public function __construct(
        ?StructuredLogger  $logger            = null,
        ?Tracer            $tracer            = null,
        ?SlowQueryDetector $slowQueryDetector = null,
        private string     $environment       = 'production'
    ) {
        $this->logger            = $logger ?? new StructuredLogger('apm');
        $this->tracer            = $tracer ?? new Tracer('netfusion');
        $this->slowQueryDetector = $slowQueryDetector ?? new SlowQueryDetector(100.0);
    }

    // ------------------------------------------------------------------
    // Factory / singleton
    // ------------------------------------------------------------------

    public static function create(string $environment = 'production'): self
    {
        return new self(environment: $environment);
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function logger(): StructuredLogger        { return $this->logger; }
    public function tracer(): Tracer                  { return $this->tracer; }
    public function slowQuery(): SlowQueryDetector    { return $this->slowQueryDetector; }
    public function getEnvironment(): string          { return $this->environment; }

    // ------------------------------------------------------------------
    // Convenience shortcuts
    // ------------------------------------------------------------------

    /**
     * Log an event at INFO level.
     */
    public function event(string $name, array $context = []): void
    {
        $this->logger->info($name, array_merge(['event' => $name], $context));
    }

    /**
     * Start a new profiler session.
     */
    public function profile(string $name = 'request'): Profiler
    {
        return new Profiler($name);
    }

    /**
     * Record and optionally log a SQL query.
     */
    public function recordQuery(string $sql, float $ms, array $bindings = []): void
    {
        $this->slowQueryDetector->record($sql, $ms, $bindings);

        if ($ms >= $this->slowQueryDetector->getThreshold()) {
            $this->logger->warning("Slow query detected: {$sql}", [
                'execution_ms' => $ms,
                'bindings'     => $bindings,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Summary
    // ------------------------------------------------------------------

    public function getSummary(): array
    {
        return [
            'environment'   => $this->environment,
            'log_count'     => $this->logger->count(),
            'span_count'    => count($this->tracer->getCompletedSpans()),
            'slow_queries'  => $this->slowQueryDetector->getSlowCount(),
            'total_queries' => $this->slowQueryDetector->getCount(),
        ];
    }
}
