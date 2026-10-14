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

use NetFusion\Core\Data\TimeSeries\Contracts\TimeSeriesStoreInterface;
use NetFusion\Core\Data\TimeSeries\DataPoint;
use NetFusion\Core\Data\TimeSeries\TimeSeries;

class ArrayTimeSeriesStore implements TimeSeriesStoreInterface
{
    /** @var array<string, DataPoint[]> */
    protected array $data = [];

    public function insert(DataPoint ...$points): bool
    {
        foreach ($points as $p) {
            $this->data[$p->metric][] = $p;
        }
        return true;
    }

    public function query(
        string $metric,
        float $from,
        float $to,
        array $tags = [],
        ?string $aggregation = null,
        ?int $stepSeconds = null
    ): TimeSeries {
        $raw = $this->data[$metric] ?? [];
        $matching = [];

        foreach ($raw as $p) {
            if ($p->timestamp >= $from && $p->timestamp <= $to) {
                if (!empty($tags)) {
                    $tagsMatch = true;
                    foreach ($tags as $k => $v) {
                        if (!isset($p->tags[$k]) || (string)$p->tags[$k] !== (string)$v) {
                            $tagsMatch = false;
                            break;
                        }
                    }
                    if (!$tagsMatch) {
                        continue;
                    }
                }
                $matching[] = $p;
            }
        }

        $series = new TimeSeries($metric, $matching);

        if ($stepSeconds !== null && $stepSeconds > 0) {
            $series = $series->window($stepSeconds, $aggregation ?? 'avg');
        }

        return $series;
    }

    public function downsample(string $metric, int $windowSeconds, string $aggregation = 'avg'): int
    {
        $points = $this->data[$metric] ?? [];
        if (empty($points)) {
            return 0;
        }

        $series = new TimeSeries($metric, $points);
        $windowed = $series->window($windowSeconds, $aggregation);

        return count($windowed);
    }

    public function prune(float $olderThan): int
    {
        $pruned = 0;
        foreach ($this->data as $metric => $points) {
            $initialCount = count($points);
            $this->data[$metric] = array_values(
                array_filter($points, fn(DataPoint $p) => $p->timestamp >= $olderThan)
            );
            $pruned += ($initialCount - count($this->data[$metric]));
        }
        return $pruned;
    }

    public function getMetrics(): array
    {
        return array_keys($this->data);
    }

    public function count(?string $metric = null): int
    {
        if ($metric !== null) {
            return count($this->data[$metric] ?? []);
        }

        $total = 0;
        foreach ($this->data as $points) {
            $total += count($points);
        }
        return $total;
    }
}
