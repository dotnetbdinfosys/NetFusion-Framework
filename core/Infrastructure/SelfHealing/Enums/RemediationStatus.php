<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Infrastructure/SelfHealing/Enums/RemediationStatus.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Infrastructure\SelfHealing\Enums;

/**
 * Status of an automated remediation execution.
 */
enum RemediationStatus: string
{
    case PENDING = 'pending';
    case CANARY_PROGRESSING = 'canary_progressing';
    case VERIFIED = 'verified';
    case FAILED = 'failed';
    case ROLLED_BACK = 'rolled_back';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::VERIFIED, self::FAILED, self::ROLLED_BACK => true,
            default => false,
        };
    }
}
