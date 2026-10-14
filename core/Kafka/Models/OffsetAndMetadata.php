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

namespace NetFusion\Core\Kafka\Models;

/**
 * Committed offset and associated user metadata.
 */
class OffsetAndMetadata
{
    public function __construct(
        public readonly int $offset,
        public readonly int $leaderEpoch = 0,
        public readonly string $metadata = '',
        public readonly int $commitTimestamp = 0
    ) {
    }

    public function toArray(): array
    {
        return [
            'offset'          => $this->offset,
            'leaderEpoch'     => $this->leaderEpoch,
            'metadata'        => $this->metadata,
            'commitTimestamp' => $this->commitTimestamp > 0 ? $this->commitTimestamp : time(),
        ];
    }
}
