<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/HRM/Routes/api_v1.php
| Module    : Modules\HRM
| Namespace : None (Module API v1 Routes)
| Purpose   : Versioned v1 REST API routing definitions for HRM & Payroll endpoints.
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
use NetFusion\Modules\HRM\Controllers\Api\EmployeeApiController;
use NetFusion\Modules\HRM\Controllers\Api\PayrollApiController;

/** @var Router $router */
$router->group(['prefix' => 'api/v1/hrm', 'name' => 'api.v1.hrm.'], function (Router $r) {
    // Employees API
    $r->get('/employees', [EmployeeApiController::class, 'index'])->name('employees.index');
    $r->post('/employees', [EmployeeApiController::class, 'store'])->name('employees.store');
    $r->get('/employees/{id}', [EmployeeApiController::class, 'show'])->name('employees.show');

    // Payroll API
    $r->get('/payrolls', [PayrollApiController::class, 'index'])->name('payrolls.index');
    $r->post('/payrolls/generate', [PayrollApiController::class, 'generate'])->name('payrolls.generate');
    $r->post('/payrolls/{id}/pay', [PayrollApiController::class, 'pay'])->name('payrolls.pay');
});
