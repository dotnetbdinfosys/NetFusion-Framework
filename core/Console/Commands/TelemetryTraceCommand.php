<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Telemetry\Context\Baggage;
use NetFusion\Core\Telemetry\TelemetryManager;
use NetFusion\Core\Tracing\TraceContext;
use Throwable;

class TelemetryTraceCommand extends Command
{
    protected string $name = 'telemetry:trace';
    protected string $description = 'Inspect active W3C distributed trace context and baggage';

    public function handle(): int
    {
        /** @var TelemetryManager $telemetry */
        $telemetry = $this->app->make(TelemetryManager::class);

        $traceId = (string)$this->argument(0);

        try {
            $context = $telemetry->getTraceContext();
            $baggage = $telemetry->getBaggage();

            if ($context === null && empty($traceId)) {
                $context = TraceContext::generate();
                $telemetry->setTraceContext($context);
            }

            $this->info("=================================================");
            $this->info("  NetFusion W3C Distributed Trace Context       ");
            $this->info("=================================================");
            $this->line("Trace ID      : " . ($context?->traceId ?? $traceId));
            $this->line("Span ID       : " . ($context?->spanId ?? 'N/A'));
            $this->line("Parent Span ID: " . ($context?->parentSpanId ?? 'root'));
            $this->line("Trace Flags   : " . ($context?->traceFlags ?? '01 (sampled)'));
            $this->line("Tracestate    : " . ($context?->tracestate ?? 'none'));
            $this->line("Traceparent   : " . ($context?->toHeader() ?? 'N/A'));

            $this->info("\n--- W3C Baggage Items ---");
            if (count($baggage) === 0) {
                $this->line("No baggage entries in current context.");
            } else {
                foreach ($baggage->getAll() as $k => $v) {
                    $this->line("  {$k} = {$v}");
                }
            }

            return 0;
        } catch (Throwable $e) {
            $this->error("Failed to inspect trace context: " . $e->getMessage());
            return 1;
        }
    }
}
