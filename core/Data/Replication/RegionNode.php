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

namespace NetFusion\Core\Data\Replication;

use JsonSerializable;

class RegionNode implements JsonSerializable
{
    /**
     * @param string   $id        e.g. 'us-east'
     * @param string   $name      e.g. 'North America East'
     * @param string   $endpoint  e.g. 'https://us-east.edge.netfusion.io'
     * @param bool     $isLocal   Whether this node is current running instance
     * @param string[] $countries Countries served by this region e.g. ['US', 'CA']
     * @param int      $weight    Load balancing weight
     * @param float    $latencyMs Measured ping latency in milliseconds
     * @param bool     $healthy   Node health state
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $endpoint,
        public readonly bool $isLocal = false,
        public readonly array $countries = [],
        public readonly int $weight = 100,
        public float $latencyMs = 0.0,
        public bool $healthy = true
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'endpoint' => $this->endpoint,
            'is_local' => $this->isLocal,
            'countries' => $this->countries,
            'weight' => $this->weight,
            'latency_ms' => $this->latencyMs,
            'healthy' => $this->healthy,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
