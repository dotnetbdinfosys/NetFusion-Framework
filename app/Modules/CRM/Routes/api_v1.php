<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/CRM/Routes/api_v1.php
| Module    : CRM
| Namespace : None (Route Definitions)
| Purpose   : API v1 routes for CRM Leads, Deals, and Helpdesk Support Tickets.
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
use NetFusion\Modules\CRM\Controllers\Api\LeadApiController;
use NetFusion\Modules\CRM\Controllers\Api\TicketApiController;

/** @var Router $router */

$router->group(['prefix' => 'api/v1/crm'], function (Router $router) {
    // Leads & Deals
    $router->get('/leads', [LeadApiController::class, 'index']);
    $router->post('/leads', [LeadApiController::class, 'store']);
    $router->patch('/leads/{id}/status', [LeadApiController::class, 'updateStatus']);
    $router->post('/deals', [LeadApiController::class, 'storeDeal']);
    $router->get('/pipeline', [LeadApiController::class, 'pipeline']);

    // Support Tickets
    $router->get('/tickets', [TicketApiController::class, 'index']);
    $router->post('/tickets', [TicketApiController::class, 'store']);
    $router->get('/tickets/{id}', [TicketApiController::class, 'show']);
    $router->post('/tickets/{id}/replies', [TicketApiController::class, 'reply']);
    $router->patch('/tickets/{id}/resolve', [TicketApiController::class, 'resolve']);
});
