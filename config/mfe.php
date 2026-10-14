<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - Micro-Frontend Configuration
 * |--------------------------------------------------------------------------
 * |
 * | Configuration settings for Micro-Frontend Architecture, Module Federation
 * | (Webpack 5), Edge SSR Fragment Stitching, and Cross-MFE Event Bus.
 * |
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Application Identity & Host Name
    |--------------------------------------------------------------------------
    */
    'name' => env('MFE_HOST_NAME', 'netfusion_host'),
    'default_render_mode' => env('MFE_DEFAULT_RENDER_MODE', 'ssr'), // 'ssr', 'csr', 'hybrid'
    'default_timeout' => (int)env('MFE_DEFAULT_TIMEOUT', 3000), // ms

    /*
    |--------------------------------------------------------------------------
    | Remote Container Configurations (Module Federation)
    |--------------------------------------------------------------------------
    */
    'remotes' => [
        // 'nav' => [
        //     'entry' => 'https://nav.example.com/remoteEntry.js',
        //     'manifest' => 'https://nav.example.com/mf-manifest.json',
        //     'exposes' => ['./Header' => './src/Header.vue'],
        //     'shared' => ['vue' => ['version' => '3.4.0', 'singleton' => true]],
        //     'timeout' => 2000,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Shared Scope Defaults
    |--------------------------------------------------------------------------
    */
    'shared' => [
        // 'react' => ['version' => '18.2.0', 'singleton' => true],
        // 'vue' => ['version' => '3.4.0', 'singleton' => true],
    ],
];
