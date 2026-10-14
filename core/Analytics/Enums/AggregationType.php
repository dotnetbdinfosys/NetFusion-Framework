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

namespace NetFusion\Core\Analytics\Enums;

/**
 * Supported aggregation functions for real-time analytical queries.
 */
enum AggregationType: string
{
    case SUM         = 'sum';
    case COUNT       = 'count';
    case MIN         = 'min';
    case MAX         = 'max';
    case AVG         = 'avg';
    case HLL_UNIQUE  = 'hll_unique'; // HyperLogLog Approximate Cardinality
}
