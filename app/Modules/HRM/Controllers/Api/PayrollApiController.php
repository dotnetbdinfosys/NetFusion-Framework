<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/HRM/Controllers/Api/PayrollApiController.php
| Module    : Modules\HRM
| Namespace : NetFusion\Modules\HRM\Controllers\Api
| Purpose   : RESTful v1 API Controller for salary generation and payslip notifications.
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

namespace NetFusion\Modules\HRM\Controllers\Api;

use NetFusion\Core\Http\ApiResponse;
use NetFusion\Core\Http\JsonResponse;
use NetFusion\Core\Http\Request;
use NetFusion\Core\OpenApi\Attributes\ApiEndpoint;
use NetFusion\Modules\HRM\Models\Employee;
use NetFusion\Modules\HRM\Models\Payroll;
use NetFusion\Modules\HRM\Notifications\PayrollProcessedNotification;

/**
 * NetFusion HRM Payroll API Controller
 *
 * @package NetFusion\Modules\HRM\Controllers\Api
 * @version 1.0.0
 */
class PayrollApiController
{
    #[ApiEndpoint(
        summary: 'List payroll records',
        description: 'Returns all generated payroll statements for the active tenant.',
        tags: ['HRM'],
        security: ['bearerAuth']
    )]
    public function index(Request $request): JsonResponse
    {
        $tenantId = tenant()?->id ?? 'tenant_default';
        $payrolls = Payroll::where('tenant_id', '=', $tenantId)->get();

        return ApiResponse::success($payrolls);
    }

    #[ApiEndpoint(
        summary: 'Generate monthly payroll',
        description: 'Calculates net salary, creates a payroll draft, and sends a notification.',
        tags: ['HRM'],
        security: ['bearerAuth']
    )]
    public function generate(Request $request): JsonResponse
    {
        $tenantId   = tenant()?->id ?? 'tenant_default';
        $employeeId = $request->int('employee_id');

        /** @var Employee|null $employee */
        $employee = Employee::find($employeeId);
        if (!$employee) {
            return ApiResponse::notFound("Employee [{$employeeId}] not found.");
        }

        $month      = $request->int('month', (int) date('m'));
        $year       = $request->int('year', (int) date('Y'));
        $basic      = (float) $employee->basic_salary;
        $allowances = (float) $request->float('allowances', 0.0);
        $deductions = (float) $request->float('deductions', 0.0);
        $netSalary  = max(0.0, $basic + $allowances - $deductions);

        $payroll = Payroll::create([
            'tenant_id'    => $tenantId,
            'employee_id'  => $employee->id,
            'month'        => $month,
            'year'         => $year,
            'basic_salary' => $basic,
            'allowances'   => $allowances,
            'deductions'   => $deductions,
            'net_salary'   => $netSalary,
            'status'       => 'processed',
        ]);

        // Dispatch multi-channel notification (mail + database) to the employee
        $employee->notify(new PayrollProcessedNotification($payroll));

        return ApiResponse::created(
            $payroll->toArray(),
            "Payroll for {$month}/{$year} generated and notification dispatched."
        );
    }

    #[ApiEndpoint(
        summary: 'Disburse payroll payment',
        description: 'Marks a processed payroll statement as officially paid.',
        tags: ['HRM'],
        security: ['bearerAuth']
    )]
    public function pay(Request $request): JsonResponse
    {
        $id = (int) $request->routeParam('id', 0);

        /** @var Payroll|null $payroll */
        $payroll = Payroll::find($id);
        if (!$payroll) {
            return ApiResponse::notFound("Payroll [{$id}] not found.");
        }

        $payroll->markAsPaid();

        return ApiResponse::success(
            $payroll->toArray(),
            200,
            ['message' => "Payroll [{$id}] has been marked as paid."]
        );
    }
}
