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

use JsonSerializable;

class DataPoint implements JsonSerializable
{
    public function __construct(
        public readonly string $metric,
        public readonly float $value,
        public readonly float $timestamp,
        public readonly array $tags = []
    ) {
    }

    public static function create(string $metric, float $value, ?float $timestamp = null, array $tags = []): self
    {
        return new self(
            metric: $metric,
            value: $value,
            timestamp: $timestamp ?? microtime(true),
            tags: $tags
        );
    }

    public function toArray(): array
    {
        return [
            'metric' => $this->metric,
            'value' => $this->value,
            'timestamp' => $this->timestamp,
            'tags' => $this->tags,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            metric: (string)($data['metric'] ?? ''),
            value: (float)($data['value'] ?? 0.0),
            timestamp: (float)($data['timestamp'] ?? microtime(true)),
            tags: (array)($data['tags'] ?? [])
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
