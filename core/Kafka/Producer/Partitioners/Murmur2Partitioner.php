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
 * Default Kafka partitioner: hashes key via 32-bit hash or round-robins when key is null.
 */
class Murmur2Partitioner implements PartitionerInterface
{
    /** @var array<string, int> Per-topic round-robin counters */
    protected array $counters = [];

    public function partition(string $topic, ?string $key, string $value, int $numPartitions): int
    {
        if ($numPartitions <= 1) {
            return 0;
        }

        if ($key !== null) {
            // Compute deterministic positive 32-bit hash
            $hash = crc32($key) & 0x7fffffff;
            return $hash % $numPartitions;
        }

        if (!isset($this->counters[$topic])) {
            $this->counters[$topic] = 0;
        }

        $part = $this->counters[$topic] % $numPartitions;
        $this->counters[$topic]++;

        return $part;
    }
}
