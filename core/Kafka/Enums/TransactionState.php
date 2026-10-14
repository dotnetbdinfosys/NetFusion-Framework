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

namespace NetFusion\Core\Kafka\Enums;

/**
 * Producer Exactly-Once Semantics (EOS) transaction lifecycle states.
 */
enum TransactionState: string
{
    case UNINITIALIZED  = 'uninitialized';
    case READY          = 'ready';
    case ONGOING        = 'ongoing';
    case PREPARE_COMMIT = 'prepare_commit';
    case PREPARE_ABORT  = 'prepare_abort';
    case COMMITTED      = 'committed';
    case ABORTED        = 'aborted';
}
