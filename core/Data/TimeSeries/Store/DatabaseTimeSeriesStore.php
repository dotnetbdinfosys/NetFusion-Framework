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

namespace NetFusion\Core\Data\TimeSeries\Store;

use PDO;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\Data\TimeSeries\Aggregator;
use NetFusion\Core\Data\TimeSeries\Contracts\TimeSeriesStoreInterface;
use NetFusion\Core\Data\TimeSeries\DataPoint;
use NetFusion\Core\Data\TimeSeries\TimeSeries;

class DatabaseTimeSeriesStore implements TimeSeriesStoreInterface
{
    protected PDO $pdo;
    protected string $rawTable;
    protected string $rollupTable;

    public function __construct(
        PDO|Connection $connection,
        string $rawTable = 'netfusion_timeseries_raw',
        string $rollupTable = 'netfusion_timeseries_rollups'
    ) {
        $this->pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;
        $this->rawTable = $rawTable;
        $this->rollupTable = $rollupTable;
    }

    public function insert(DataPoint ...$points): bool
    {
        if (empty($points)) {
            return true;
        }

        $sql = "INSERT INTO {$this->rawTable} (id, metric, timestamp, value, tags_json, created_at) VALUES ";
        $placeholders = [];
        $params = [];
        $now = date('Y-m-d H:i:s');

        foreach ($points as $idx => $p) {
            $id = bin2hex(random_bytes(16));
            $placeholders[] = "(:id_{$idx}, :metric_{$idx}, :ts_{$idx}, :val_{$idx}, :tags_{$idx}, :created_{$idx})";
            $params["id_{$idx}"] = $id;
            $params["metric_{$idx}"] = $p->metric;
            $params["ts_{$idx}"] = $p->timestamp;
            $params["val_{$idx}"] = $p->value;
            $params["tags_{$idx}"] = json_encode($p->tags);
            $params["created_{$idx}"] = $now;
        }

        $sql .= implode(', ', $placeholders);
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function query(
        string $metric,
        float $from,
        float $to,
        array $tags = [],
        ?string $aggregation = null,
        ?int $stepSeconds = null
    ): TimeSeries {
        $stmt = $this->pdo->prepare("SELECT metric, timestamp, value, tags_json FROM {$this->rawTable} WHERE metric = :metric AND timestamp >= :from_ts AND timestamp <= :to_ts ORDER BY timestamp ASC");
        $stmt->execute([
            'metric' => $metric,
            'from_ts' => $from,
            'to_ts' => $to,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $matching = [];

        foreach ($rows as $row) {
            $rowTags = json_decode($row['tags_json'] ?? '{}', true) ?: [];
            if (!empty($tags)) {
                $match = true;
                foreach ($tags as $k => $v) {
                    if (!isset($rowTags[$k]) || (string)$rowTags[$k] !== (string)$v) {
                        $match = false;
                        break;
                    }
                }
                if (!$match) {
                    continue;
                }
            }

            $matching[] = new DataPoint(
                metric: $row['metric'],
                value: (float)$row['value'],
                timestamp: (float)$row['timestamp'],
                tags: $rowTags
            );
        }

        $series = new TimeSeries($metric, $matching);

        if ($stepSeconds !== null && $stepSeconds > 0) {
            $series = $series->window($stepSeconds, $aggregation ?? 'avg');
        }

        return $series;
    }

    public function downsample(string $metric, int $windowSeconds, string $aggregation = 'avg'): int
    {
        $stmt = $this->pdo->prepare("SELECT timestamp, value FROM {$this->rawTable} WHERE metric = :metric ORDER BY timestamp ASC");
        $stmt->execute(['metric' => $metric]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return 0;
        }

        $buckets = [];
        foreach ($rows as $r) {
            $bucketTs = floor((float)$r['timestamp'] / $windowSeconds) * $windowSeconds;
            $buckets[$bucketTs][] = (float)$r['value'];
        }

        $insertedRollups = 0;
        foreach ($buckets as $ts => $vals) {
            $id = bin2hex(random_bytes(16));
            $avg = Aggregator::avg($vals);
            $min = Aggregator::min($vals);
            $max = Aggregator::max($vals);
            $sum = Aggregator::sum($vals);
            $cnt = count($vals);

            $stmtRollup = $this->pdo->prepare("INSERT INTO {$this->rollupTable} (
                id, metric, bucket_timestamp, window_size, avg_val, min_val, max_val, sum_val, count_val, tags_json
            ) VALUES (
                :id, :metric, :bucket_ts, :win_size, :avg_val, :min_val, :max_val, :sum_val, :count_val, :tags_json
            )");

            if ($stmtRollup->execute([
                'id' => $id,
                'metric' => $metric,
                'bucket_ts' => (float)$ts,
                'win_size' => $windowSeconds,
                'avg_val' => $avg,
                'min_val' => $min,
                'max_val' => $max,
                'sum_val' => $sum,
                'count_val' => $cnt,
                'tags_json' => '{}',
            ])) {
                $insertedRollups++;
            }
        }

        return $insertedRollups;
    }

    public function prune(float $olderThan): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->rawTable} WHERE timestamp < :ts");
        $stmt->execute(['ts' => $olderThan]);
        return $stmt->rowCount();
    }

    public function getMetrics(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT metric FROM {$this->rawTable}");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }

    public function count(?string $metric = null): int
    {
        if ($metric !== null) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->rawTable} WHERE metric = :m");
            $stmt->execute(['m' => $metric]);
            return (int)$stmt->fetchColumn();
        }

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$this->rawTable}");
        return (int)($stmt ? $stmt->fetchColumn() : 0);
    }
}
