<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Commerce/config.php
| Module    : Commerce
| Namespace : NetFusion\Modules\Commerce
| Purpose   : Commerce and POS module configuration
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
    'currency' => [
        'code'   => 'BDT',
        'symbol' => '৳',
    ],
    'tax_rate' => 0.05, // 5% default VAT
    'default_warehouse_id' => 1,
    'pos' => [
        'allow_negative_stock' => false,
        'default_cash_account_code' => '1000',
        'default_sales_account_code' => '4000',
        'default_ar_account_code' => '1100',
    ],
];
