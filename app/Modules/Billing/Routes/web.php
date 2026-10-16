<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Routes/web.php
| Module    : Billing
| Namespace : None (Route Definitions)
| Purpose   : Web routes for SaaS Billing and Subscription Management module.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

use NetFusion\Core\Routing\Router;
use NetFusion\Modules\Billing\Controllers\BillingController;

/** @var Router $router */

$router->get('/billing', [BillingController::class, 'index'])->name('billing.dashboard');
$router->get('/billing/invoices', [BillingController::class, 'invoices'])->name('billing.invoices');
$router->get('/billing/invoices/edit', [BillingController::class, 'editInvoice'])->name('billing.invoices.edit');
$router->post('/billing/invoices/edit', [BillingController::class, 'updateInvoice']);
$router->post('/billing/invoices/update', [BillingController::class, 'updateInvoice'])->name('billing.invoices.update');

$router->get('/billing/subscriptions', [BillingController::class, 'subscriptions'])->name('billing.subscriptions');
$router->post('/billing/subscriptions/update', [BillingController::class, 'updateSubscription'])->name('billing.subscriptions.update');

$router->get('/billing/plans', [BillingController::class, 'plans'])->name('billing.plans');
$router->get('/billing/plans/edit', [BillingController::class, 'editPlan'])->name('billing.plans.edit');
$router->post('/billing/plans/update', [BillingController::class, 'updatePlan'])->name('billing.plans.update');

$router->get('/billing/payments', [BillingController::class, 'payments'])->name('billing.payments');
$router->get('/billing/overdue', [BillingController::class, 'overdue'])->name('billing.overdue');