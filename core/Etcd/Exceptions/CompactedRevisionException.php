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

namespace NetFusion\Core\Etcd\Exceptions;

/**
 * Thrown when attempting to query a historical revision that has been compacted.
 */
class CompactedRevisionException extends EtcdException
{
    public function __construct(int $requestedRevision, int $compactRevision)
    {
        parent::__construct(
            sprintf(
                'mvcc: required revision %d has been compacted (current compact revision: %d)',
                $requestedRevision,
                $compactRevision
            )
        );
    }
}
