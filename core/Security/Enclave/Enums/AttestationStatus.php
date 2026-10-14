<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Enclave/Enums/AttestationStatus.php
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

namespace NetFusion\Core\Security\Enclave\Enums;

/**
 * Verification outcome of a remote enclave attestation quote.
 */
enum AttestationStatus: string
{
    case VERIFIED = 'verified';
    case EXPIRED = 'expired';
    case TAMPERED = 'tampered';
    case UNTRUSTED = 'untrusted';

    public function isValid(): bool
    {
        return $this === self::VERIFIED;
    }
}
