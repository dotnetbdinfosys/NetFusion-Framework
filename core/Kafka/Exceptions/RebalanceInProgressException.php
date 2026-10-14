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
 * Thrown when consumer commit/poll fails because group rebalance is underway.
 */
class RebalanceInProgressException extends KafkaException
{
    public function __construct(string $groupId)
    {
        parent::__construct(sprintf('Consumer group "%s" is currently undergoing rebalance', $groupId));
    }
}
