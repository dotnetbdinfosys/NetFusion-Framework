<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/CRM/Routes/web.php
| Module    : CRM
| Namespace : None (Route Definitions)
| Purpose   : Web routes for Customer Relationship Management (CRM) module.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

use NetFusion\Core\Routing\Router;
use NetFusion\Modules\CRM\Controllers\CrmDashboardController;

/** @var Router $router */

$router->get('/crm', [CrmDashboardController::class, 'index'])->name('crm.dashboard');
$router->get('/crm/leads', [CrmDashboardController::class, 'leads'])->name('crm.leads');
$router->get('/crm/leads/edit', [CrmDashboardController::class, 'editLead'])->name('crm.leads.edit');
$router->post('/crm/leads/update', [CrmDashboardController::class, 'updateLead'])->name('crm.leads.update');
$router->get('/crm/deals', [CrmDashboardController::class, 'deals'])->name('crm.deals');
$router->get('/crm/companies', [CrmDashboardController::class, 'companies'])->name('crm.companies');
$router->get('/crm/contacts', [CrmDashboardController::class, 'contacts'])->name('crm.contacts');
$router->get('/crm/tickets', [CrmDashboardController::class, 'tickets'])->name('crm.tickets');
$router->get('/crm/analytics', [CrmDashboardController::class, 'analytics'])->name('crm.analytics');
