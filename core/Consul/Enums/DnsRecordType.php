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

namespace NetFusion\Core\Consul\Enums;

/**
 * Supported RFC 1035 DNS record types.
 */
enum DnsRecordType: int
{
    case A     = 1;
    case CNAME = 5;
    case TXT   = 16;
    case SRV   = 33;
    case ANY   = 255;

    /**
     * Parse string name to enum case.
     */
    public static function fromName(string $name): ?self
    {
        $upper = strtoupper($name);
        foreach (self::cases() as $case) {
            if ($case->name === $upper) {
                return $case;
            }
        }
        return null;
    }
}
