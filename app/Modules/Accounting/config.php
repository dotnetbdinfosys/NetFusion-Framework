<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Accounting/config.php
| Module    : Accounting
| Namespace : None (Config Array)
| Purpose   : Configuration file for Double-Entry Accounting and Ledger module.
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
    'currency'       => env('ACCOUNTING_CURRENCY', 'USD'),
    'currency_symbol'=> env('ACCOUNTING_CURRENCY_SYMBOL', '$'),
    'fiscal_year_start' => env('FISCAL_YEAR_START', '01-01'),
    'auto_post_billing' => true,
    'auto_post_payroll' => true,
];
