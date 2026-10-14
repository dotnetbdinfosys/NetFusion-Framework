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

namespace NetFusion\Core\Data\TimeSeries;

use Countable;
use IteratorAggregate;
use ArrayIterator;
use JsonSerializable;
use Traversable;

class TimeSeries implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var DataPoint[] */
    protected array $points = [];

    public function __construct(
        public readonly string $metric,
        array $points = []
    ) {
        $this->addPoints($points);
    }

    public function addPoint(DataPoint $point): self
    {
        $this->points[] = $point;
        return $this;
    }

    public function addPoints(array $points): self
    {
        foreach ($points as $p) {
            if ($p instanceof DataPoint) {
                $this->points[] = $p;
            }
        }
        $this->sort();
        return $this;
    }

    public function sort(): self
    {
        usort($this->points, fn(DataPoint $a, DataPoint $b) => $a->timestamp <=> $b->timestamp);
        return $this;
    }

    /**
     * @return DataPoint[]
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    /**
     * @return float[]
     */
    public function getValues(): array
    {
        return array_map(fn(DataPoint $p) => $p->value, $this->points);
    }

    /**
     * @return float[]
     */
    public function getTimestamps(): array
    {
        return array_map(fn(DataPoint $p) => $p->timestamp, $this->points);
    }

    public function count(): int
    {
        return count($this->points);
    }

    public function isEmpty(): bool
    {
        return empty($this->points);
    }

    public function latest(): ?DataPoint
    {
        return empty($this->points) ? null : end($this->points);
    }

    public function earliest(): ?DataPoint
    {
        return empty($this->points) ? null : reset($this->points);
    }

    public function aggregate(string $func = Aggregator::AGG_AVG): float
    {
        return Aggregator::aggregate($this->getValues(), $func);
    }

    public function slice(float $from, float $to): self
    {
        $filtered = array_filter($this->points, fn(DataPoint $p) => $p->timestamp >= $from && $p->timestamp <= $to);
        return new self($this->metric, array_values($filtered));
    }

    /**
     * Resample / bucket data into fixed step time intervals.
     */
    public function window(int $stepSeconds, string $aggregation = Aggregator::AGG_AVG): self
    {
        if ($stepSeconds <= 0 || empty($this->points)) {
            return $this;
        }

        $buckets = [];
        foreach ($this->points as $p) {
            $bucketTs = floor($p->timestamp / $stepSeconds) * $stepSeconds;
            $buckets[$bucketTs][] = $p->value;
        }

        $aggregatedPoints = [];
        foreach ($buckets as $ts => $vals) {
            $aggVal = Aggregator::aggregate($vals, $aggregation);
            $aggregatedPoints[] = new DataPoint($this->metric, $aggVal, (float)$ts);
        }

        return new self($this->metric, $aggregatedPoints);
    }

    public function rate(): self
    {
        return new self($this->metric . '_rate', Aggregator::rate($this->points));
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->points);
    }

    public function toArray(): array
    {
        return [
            'metric' => $this->metric,
            'count' => count($this->points),
            'points' => array_map(fn(DataPoint $p) => $p->toArray(), $this->points),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
