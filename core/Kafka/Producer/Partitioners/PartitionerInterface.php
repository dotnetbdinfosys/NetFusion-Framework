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
 * Strategy interface for selecting target partition for a producer record.
 */
interface PartitionerInterface
{
    /**
     * Compute partition index for the record.
     */
    public function partition(string $topic, ?string $key, string $value, int $numPartitions): int;
}
