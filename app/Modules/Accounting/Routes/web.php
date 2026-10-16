<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Accounting/Routes/web.php
| Module    : Accounting
| Namespace : None (Route Definitions)
| Purpose   : Web routes for the Accounting and Financial Ledger module.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.3.5
|
|--------------------------------------------------------------------------
*/

use NetFusion\Core\Routing\Router;
use NetFusion\Modules\Accounting\Controllers\AccountingDashboardController;

/** @var Router $router */

$router->get('/accounting', [AccountingDashboardController::class, 'index'])->name('accounting.dashboard');
$router->get('/accounting/chart-of-accounts', [AccountingDashboardController::class, 'chartOfAccounts'])->name('accounting.chart');
$router->get('/accounting/journal', [AccountingDashboardController::class, 'journal'])->name('accounting.journal');
$router->get('/accounting/ledger', [AccountingDashboardController::class, 'ledger'])->name('accounting.ledger');
$router->get('/accounting/trial-balance', [AccountingDashboardController::class, 'trialBalance'])->name('accounting.trial-balance');
$router->get('/accounting/income-statement', [AccountingDashboardController::class, 'incomeStatement'])->name('accounting.income-statement');
$router->get('/accounting/balance-sheet', [AccountingDashboardController::class, 'balanceSheet'])->name('accounting.balance-sheet');
$router->get('/accounting/tax-report', [AccountingDashboardController::class, 'taxReport'])->name('accounting.tax-report');
