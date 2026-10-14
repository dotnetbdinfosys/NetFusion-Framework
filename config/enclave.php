<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : config/enclave.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Confidential Computing & Hardware Enclave Configuration
    |--------------------------------------------------------------------------
    */

    'default_type' => env('ENCLAVE_DEFAULT_TYPE', 'intel_sgx'),

    'hardware_secret' => env('ENCLAVE_HW_SECRET', 'DotNetBD-Confidential-Hardware-Platform-Root-Key'),

    'master_seed' => env('ENCLAVE_MASTER_SEED', 'DotNetBD-Confidential-Hardware-Root-Sealing-Seed'),

    'quote_ttl_seconds' => (int)env('ENCLAVE_QUOTE_TTL', 3600),

    'default_memory_limit_mb' => (int)env('ENCLAVE_MEM_LIMIT_MB', 128),
];
