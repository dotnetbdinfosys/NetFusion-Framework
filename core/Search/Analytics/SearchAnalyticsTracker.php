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

namespace NetFusion\Core\Search\Analytics;

class SearchAnalyticsTracker
{
    /**
     * @var array<int, array{query: string, index_name: string, hits_count: int, latency_ms: float, clicked_doc_id: string|null, timestamp: string}>
     */
    protected array $events = [];

    public function __construct(
        protected ?\PDO $pdo = null
    ) {
    }

    public function track(string $indexName, string $query, int $hitsCount, float $latencyMs, ?string $clickedDocId = null): void
    {
        $event = [
            'query' => trim($query),
            'index_name' => $indexName,
            'hits_count' => $hitsCount,
            'latency_ms' => $latencyMs,
            'clicked_doc_id' => $clickedDocId,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $this->events[] = $event;

        if ($this->pdo !== null) {
            $this->persistToDatabase($event);
        }
    }

    public function trackQuery(string $query, int $hitsCount = 1, float $latencyMs = 0.0, string $indexName = 'default'): void
    {
        $this->track($indexName, $query, $hitsCount, $latencyMs);
    }

    /**
     * Record user clicking a search result document.
     */
    public function recordClick(string $indexName, string $query, string $documentId): void
    {
        $this->track($indexName, $query, 1, 0.0, $documentId);
    }

    /**
     * Get aggregate search analytics summary metrics.
     *
     * @return array{total_queries: int, zero_result_queries: int, avg_latency_ms: float, top_queries: array<string, int>}
     */
    public function getMetrics(): array
    {
        $total = count($this->events);
        if ($total === 0) {
            return [
                'total_queries' => 0,
                'zero_result_queries' => 0,
                'avg_latency_ms' => 0.0,
                'top_queries' => [],
            ];
        }

        $zeroCount = 0;
        $totalLatency = 0.0;
        $queryFreq = [];

        foreach ($this->events as $e) {
            if ($e['hits_count'] === 0) {
                $zeroCount++;
            }
            $totalLatency += $e['latency_ms'];
            $q = $e['query'];
            if ($q !== '') {
                $queryFreq[$q] = ($queryFreq[$q] ?? 0) + 1;
            }
        }

        arsort($queryFreq);

        return [
            'total_queries' => $total,
            'zero_result_queries' => $zeroCount,
            'avg_latency_ms' => round($totalLatency / $total, 3),
            'top_queries' => array_slice($queryFreq, 0, 10, true),
        ];
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    protected function persistToDatabase(array $event): void
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO nf_search_analytics 
                (index_name, query_string, hits_count, latency_ms, clicked_doc_id, created_at)
                VALUES (:index_name, :query_string, :hits_count, :latency_ms, :clicked_doc_id, :created_at)");
            $stmt->execute([
                ':index_name' => $event['index_name'],
                ':query_string' => $event['query'],
                ':hits_count' => $event['hits_count'],
                ':latency_ms' => $event['latency_ms'],
                ':clicked_doc_id' => $event['clicked_doc_id'],
                ':created_at' => $event['timestamp'],
            ]);
        } catch (\Throwable) {
        }
    }
}
