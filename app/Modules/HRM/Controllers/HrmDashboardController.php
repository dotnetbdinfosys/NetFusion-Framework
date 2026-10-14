<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/HRM/Controllers/HrmDashboardController.php
| Module    : Modules\HRM
| Namespace : NetFusion\Modules\HRM\Controllers
| Purpose   : Web dashboard controller rendering HRM and payroll metrics.
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

namespace NetFusion\Modules\HRM\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Modules\HRM\Models\Department;
use NetFusion\Modules\HRM\Models\Employee;
use NetFusion\Modules\HRM\Models\Payroll;

/**
 * NetFusion HRM Dashboard Controller
 *
 * @package NetFusion\Modules\HRM\Controllers
 * @version 1.0.0
 */
class HrmDashboardController
{
    /**
     * Display HRM overview dashboard.
     */
    public function index(Request $request): Response
    {
        $tenantId = tenant()?->id ?? 'tenant_default';

        $employeeCount = Employee::where('tenant_id', '=', $tenantId)->count();
        $departmentCount = Department::where('tenant_id', '=', $tenantId)->count();
        $payrollCount = Payroll::where('tenant_id', '=', $tenantId)->count();

        $employees = Employee::where('tenant_id', '=', $tenantId)->get();
        $payrolls = Payroll::where('tenant_id', '=', $tenantId)->get();

        $totalDisbursed = 0.0;
        foreach ($payrolls as $p) {
            if ($p['status'] === 'paid') {
                $totalDisbursed += (float) $p['net_salary'];
            }
        }

        return Response::view('hrm::dashboard', [
            'tenantId'        => $tenantId,
            'employeeCount'   => $employeeCount,
            'departmentCount' => $departmentCount,
            'payrollCount'    => $payrollCount,
            'totalDisbursed'  => $totalDisbursed,
            'employees'       => $employees,
            'currency'        => config('hrm.currency', 'BDT'),
        ]);
    }
}
