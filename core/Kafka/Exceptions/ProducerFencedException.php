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
 * Thrown when a transactional or idempotent producer is superseded by a newer epoch.
 */
class ProducerFencedException extends KafkaException
{
    public function __construct(int $producerId, int $currentEpoch, int $expectedEpoch)
    {
        parent::__construct(
            sprintf(
                'Producer %d with epoch %d has been fenced by newer epoch %d',
                $producerId,
                $expectedEpoch,
                $currentEpoch
            )
        );
    }
}
