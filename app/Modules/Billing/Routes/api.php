<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Routes/api.php
| Module    : Modules\Billing
| Namespace : None (Module API Routes)
| Purpose   : REST API routing definitions for SaaS Subscription & Billing endpoints.
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

// Public / Tenant Plan retrieval
$router->get('/plans', [SubscriptionApiController::class, 'plans'])->name('api.billing.plans');

// Tenant Subscription Management
$router->post('/subscribe', [SubscriptionApiController::class, 'subscribe'])->name('api.billing.subscribe');
$router->get('/invoices', [SubscriptionApiController::class, 'invoices'])->name('api.billing.invoices');
$router->post('/invoices/{id}/pay', [SubscriptionApiController::class, 'pay'])->name('api.billing.invoices.pay');