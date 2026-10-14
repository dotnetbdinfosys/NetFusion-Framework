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

namespace NetFusion\Core\Kafka\Exceptions;

/**
 * Thrown when requested fetch offset is lower than log start offset or higher than log end offset.
 */
class OffsetOutOfRangeException extends KafkaException
{
    public function __construct(string $topic, int $partition, int $requestedOffset, int $logStart, int $logEnd)
    {
        parent::__construct(
            sprintf(
                'Fetch offset %d is out of range for %s-%d [valid range: %d to %d]',
                $requestedOffset,
                $topic,
                $partition,
                $logStart,
                $logEnd
            )
        );
    }
}
