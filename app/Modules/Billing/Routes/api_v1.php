<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Routes/api_v1.php
| Module    : Modules\Billing
| Namespace : None (Module API v1 Routes)
| Purpose   : Versioned v1 REST API routing definitions for SaaS Subscription & Billing endpoints.
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
use NetFusion\Modules\Billing\Controllers\Api\SubscriptionApiController;

/** @var Router $router */

$router->group(['prefix' => 'api/v1/billing', 'name' => 'api.v1.billing.'], function (Router $r) {
    $r->get('/plans', [SubscriptionApiController::class, 'plans'])->name('plans');
    $r->post('/subscribe', [SubscriptionApiController::class, 'subscribe'])->name('subscribe');
    $r->get('/invoices', [SubscriptionApiController::class, 'invoices'])->name('invoices');
    $r->post('/invoices/{id}/pay', [SubscriptionApiController::class, 'pay'])->name('invoices.pay');
});
