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
 * Time granularity for analytical bucketing.
 */
enum Granularity: string
{
    case ALL    = 'all';
    case SECOND = 'second';
    case MINUTE = 'minute';
    case HOUR   = 'hour';
    case DAY    = 'day';
    case WEEK   = 'week';
    case MONTH  = 'month';

    /**
     * Truncate timestamp to granularity bucket start (seconds).
     */
    public function bucket(int $timestamp): int
    {
        return match ($this) {
            self::ALL    => 0,
            self::SECOND => $timestamp,
            self::MINUTE => (int)floor($timestamp / 60) * 60,
            self::HOUR   => (int)floor($timestamp / 3600) * 3600,
            self::DAY    => (int)floor($timestamp / 86400) * 86400,
            self::WEEK   => (int)floor($timestamp / (86400 * 7)) * (86400 * 7),
            self::MONTH  => (int)strtotime(date('Y-m-01 00:00:00', $timestamp)),
        };
    }
}
