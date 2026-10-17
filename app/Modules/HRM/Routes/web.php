<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/HRM/Routes/web.php
| Module    : HRM
| Namespace : None (Route Definitions)
| Purpose   : Web routes for Human Resource Management (HRM) module.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

use NetFusion\Core\Routing\Router;
use NetFusion\Modules\HRM\Controllers\HrmDashboardController;

/** @var Router $router */

$router->get('/hrm', [HrmDashboardController::class, 'index'])->name('hrm.dashboard');
$router->get('/hrm/employees', [HrmDashboardController::class, 'employees'])->name('hrm.employees');
$router->get('/hrm/employees/edit', [HrmDashboardController::class, 'editEmployee'])->name('hrm.employees.edit');
$router->post('/hrm/employees/update', [HrmDashboardController::class, 'updateEmployee'])->name('hrm.employees.update');
$router->post('/hrm/employees/delete', [HrmDashboardController::class, 'deleteEmployee'])->name('hrm.employees.delete');
$router->get('/hrm/departments', [HrmDashboardController::class, 'departments'])->name('hrm.departments');
$router->get('/hrm/attendance', [HrmDashboardController::class, 'attendance'])->name('hrm.attendance');
$router->get('/hrm/leave', [HrmDashboardController::class, 'leave'])->name('hrm.leave');
$router->get('/hrm/payroll', [HrmDashboardController::class, 'payroll'])->name('hrm.payroll');
$router->get('/hrm/performance', [HrmDashboardController::class, 'performance'])->name('hrm.performance');
