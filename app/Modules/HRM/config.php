<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/HRM/config.php
| Module    : Modules\HRM
| Namespace : None (Config Array)
| Purpose   : HRM & Payroll module configuration settings.
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
    | Currency & Financial Settings
    |--------------------------------------------------------------------------
    */
    'currency' => env('HRM_CURRENCY', 'BDT'),

    /*
    |--------------------------------------------------------------------------
    | Default Tax & Deduction Percent
    |--------------------------------------------------------------------------
    */
    'default_tax_rate' => (float) env('HRM_DEFAULT_TAX_RATE', 5.0),

    /*
    |--------------------------------------------------------------------------
    | Automated Monthly Payroll Generation Day
    |--------------------------------------------------------------------------
    */
    'auto_payroll_day' => (int) env('HRM_AUTO_PAYROLL_DAY', 28),

];
