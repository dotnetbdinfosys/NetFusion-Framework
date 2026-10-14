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

namespace NetFusion\Core\Network\Ebpf\Observability;

/**
 * L7 Protocol & HTTP Probe Inspector (uprobe/kprobe simulator).
 * Decodes HTTP/1.1 methods, status codes, and measures application latency without application modification.
 */
class HttpInspector
{
    /** @var array<array{method: string, uri: string, status: int, latency_ms: float, timestamp: float}> */
    private array $requests = [];

    public function recordHttpTransaction(
        string $method,
        string $uri,
        int $statusCode,
        float $latencyMs
    ): array {
        $entry = [
            'method'     => strtoupper($method),
            'uri'        => $uri,
            'status'     => $statusCode,
            'latency_ms' => round($latencyMs, 2),
            'timestamp'  => microtime(true),
        ];

        $this->requests[] = $entry;
        return $entry;
    }

    public function getRequests(): array
    {
        return $this->requests;
    }

    public function getAverageLatency(): float
    {
        if (empty($this->requests)) {
            return 0.0;
        }
        $total = array_sum(array_column($this->requests, 'latency_ms'));
        return round($total / count($this->requests), 2);
    }

    public function clear(): void
    {
        $this->requests = [];
    }
}
