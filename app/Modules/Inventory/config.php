<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Inventory/config.php
| Module    : Inventory
| Namespace : None (Config Array)
| Purpose   : Configuration for Inventory and Multi-Warehouse Supply Chain.
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
    'valuation_method'      => env('INVENTORY_VALUATION', 'weighted_average'), // fifo, weighted_average
    'low_stock_notification'=> true,
    'default_currency'      => env('INVENTORY_CURRENCY', 'USD'),
];
