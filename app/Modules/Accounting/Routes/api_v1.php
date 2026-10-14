<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Accounting/Routes/api_v1.php
| Module    : Accounting
| Namespace : None (Route Definitions)
| Purpose   : Versioned REST API routes for Journal Entries and Financial Reports.
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

use NetFusion\Core\Routing\Router;
use NetFusion\Modules\Accounting\Controllers\Api\JournalApiController;
use NetFusion\Modules\Accounting\Controllers\Api\ReportApiController;

/** @var Router $router */

$router->group(['prefix' => 'api/v1/accounting'], function (Router $router) {
    // Journal Entries
    $router->get('/journals', [JournalApiController::class, 'index']);
    $router->post('/journals', [JournalApiController::class, 'store']);
    $router->post('/journals/{id}/post', [JournalApiController::class, 'post']);

    // Financial Reports
    $router->get('/reports/trial-balance', [ReportApiController::class, 'trialBalance']);
    $router->get('/reports/income-statement', [ReportApiController::class, 'incomeStatement']);
    $router->get('/reports/balance-sheet', [ReportApiController::class, 'balanceSheet']);
});
