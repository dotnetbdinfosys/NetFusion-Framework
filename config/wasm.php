<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
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

return [
    /*
    |--------------------------------------------------------------------------
    | Default Fuel Limit
    |--------------------------------------------------------------------------
    | Maximum instruction count allowed per Wasm invocation before a
    | FuelExhausted trap is triggered to protect against infinite loops.
    */
    'default_fuel' => (int) env('WASM_DEFAULT_FUEL', 1_000_000),

    /*
    |--------------------------------------------------------------------------
    | Maximum Memory Pages
    |--------------------------------------------------------------------------
    | The maximum linear memory pages allowed for a single module instance.
    | 1 page = 64 KiB (64 pages = 4 MB).
    */
    'max_memory_pages' => (int) env('WASM_MAX_MEMORY_PAGES', 64),

    /*
    |--------------------------------------------------------------------------
    | Enable WASI Preview 1
    |--------------------------------------------------------------------------
    | Whether the WebAssembly System Interface (WASI) provider is enabled
    | by default on new instances for stdout/stderr capturing and clocks.
    */
    'enable_wasi' => (bool) env('WASM_ENABLE_WASI', true),

    /*
    |--------------------------------------------------------------------------
    | Module Storage Directory
    |--------------------------------------------------------------------------
    | Local path where uploaded or compiled .wasm modules are persisted.
    */
    'modules_path' => storage_path('wasm/modules'),
];
