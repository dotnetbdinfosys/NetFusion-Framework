<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework Configuration
|--------------------------------------------------------------------------
|
| File      : config/auth_policy.php
| Module    : Policy-Based Access Control (PBAC), ABAC & ReBAC
| Purpose   : Authorization engine storage, Zanzibar relations and default rules.
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

return [

    /*
    |--------------------------------------------------------------------------
    | Default Policy Storage Driver
    |--------------------------------------------------------------------------
    | Supported: "array", "database"
    */
    'policy_store' => [
        'default' => env('AUTH_POLICY_STORE', 'array'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default ReBAC (Zanzibar) Tuple Storage Driver
    |--------------------------------------------------------------------------
    | Supported: "array", "database"
    */
    'rebac_store' => [
        'default' => env('AUTH_REBAC_STORE', 'array'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Evaluation Effect
    |--------------------------------------------------------------------------
    | "DENY" (secure by default) or "ALLOW"
    */
    'default_effect' => 'DENY',

];
