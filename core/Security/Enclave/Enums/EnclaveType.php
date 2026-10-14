<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Enclave/Enums/EnclaveType.php
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
 * Supported Confidential Computing Hardware Enclave architectures.
 */
enum EnclaveType: string
{
    case INTEL_SGX = 'intel_sgx';
    case AMD_SEV_SNP = 'amd_sev_snp';
    case AWS_NITRO = 'aws_nitro';
    case SIMULATED = 'simulated';

    public function isHardwareBacked(): bool
    {
        return $this !== self::SIMULATED;
    }
}
