<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Kafka\Producer\Partitioners;

/**
 * Round-robin partitioner distributing records uniformly across all partitions.
 */
class RoundRobinPartitioner implements PartitionerInterface
{
    /** @var array<string, int> */
    protected array $counters = [];

    public function partition(string $topic, ?string $key, string $value, int $numPartitions): int
    {
        if ($numPartitions <= 1) {
            return 0;
        }

        if (!isset($this->counters[$topic])) {
            $this->counters[$topic] = 0;
        }

        $part = $this->counters[$topic] % $numPartitions;
        $this->counters[$topic]++;

        return $part;
    }
}
