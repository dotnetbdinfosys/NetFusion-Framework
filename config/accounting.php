<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework — Accounting Configuration
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

return [

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The ISO 4217 currency code used as the functional currency.
    | All monetary amounts without an explicit currency use this value.
    |
    */
    'currency' => env('ACCOUNTING_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Fiscal Year
    |--------------------------------------------------------------------------
    |
    | The start month of the fiscal year (1 = January, 4 = April).
    | Used by reports to determine default date ranges.
    |
    */
    'fiscal_year_start_month' => 1,

    /*
    |--------------------------------------------------------------------------
    | Chart of Accounts Seed
    |--------------------------------------------------------------------------
    |
    | Whether to automatically seed the default Chart of Accounts
    | when AccountingManager::create() is called without a custom CoA.
    |
    */
    'seed_defaults' => true,

    /*
    |--------------------------------------------------------------------------
    | Journal Numbering
    |--------------------------------------------------------------------------
    |
    | Prefix for auto-generated journal entry reference numbers.
    |
    */
    'journal_prefix' => 'JE',

    /*
    |--------------------------------------------------------------------------
    | Closing Account Codes
    |--------------------------------------------------------------------------
    |
    | The account codes used for period-end closing entries.
    |
    */
    'income_summary_code'    => '3003',
    'retained_earnings_code' => '3002',

    /*
    |--------------------------------------------------------------------------
    | Decimal Precision
    |--------------------------------------------------------------------------
    |
    | Internal BCMath precision for monetary calculations.
    | Display is always rounded to 2 decimal places.
    |
    */
    'precision' => 6,

];
