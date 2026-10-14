<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : config/wasm_component.php
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
     | WebAssembly Component Model & WIT Settings
     |--------------------------------------------------------------------------
     */
    'default_memory_pages' => 2,
    'max_memory_pages'     => 32,
    'enable_wasi_p2'       => true,
    'canonical_abi'        => [
        'string_encoding'  => 'utf8',
        'default_align'    => 4,
    ],
    'storage_path'         => 'storage/wasm/components',
];
