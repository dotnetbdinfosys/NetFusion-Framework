<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/HRM/Controllers/Api/EmployeeApiController.php
| Module    : Modules\HRM
| Namespace : NetFusion\Modules\HRM\Controllers\Api
| Purpose   : RESTful v1 API Controller managing Employee records with OpenAPI docs.
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

/**
 * NetFusion HRM Employee API Controller
 *
 * @package NetFusion\Modules\HRM\Controllers\Api
 * @version 1.0.0
 */
class EmployeeApiController
{
    #[ApiEndpoint(
        summary: 'List all employees',
        description: 'Returns all employees for the active tenant context.',
        tags: ['HRM'],
        security: ['bearerAuth']
    )]
    public function index(Request $request): JsonResponse
    {
        $tenantId = tenant()?->id ?? 'tenant_default';
        $employees = Employee::where('tenant_id', '=', $tenantId)->get();

        return ApiResponse::success($employees);
    }

    #[ApiEndpoint(
        summary: 'Create an employee',
        description: 'Registers a new employee into a department.',
        tags: ['HRM'],
        security: ['bearerAuth']
    )]
    public function store(Request $request): JsonResponse
    {
        $tenantId = tenant()?->id ?? 'tenant_default';

        $employee = Employee::create([
            'tenant_id'     => $tenantId,
            'department_id' => $request->int('department_id', 1),
            'employee_code' => (string) ($request->post('employee_code') ?: 'EMP-' . strtoupper(bin2hex(random_bytes(3)))),
            'first_name'    => (string) $request->post('first_name', 'Unnamed'),
            'last_name'     => (string) $request->post('last_name', 'Employee'),
            'email'         => (string) $request->post('email', 'employee@domain.com'),
            'phone'         => (string) $request->post('phone', ''),
            'designation'   => (string) $request->post('designation', 'Staff'),
            'basic_salary'  => (float) $request->float('basic_salary', 30000.0),
            'joined_date'   => (string) ($request->post('joined_date') ?: date('Y-m-d')),
            'status'        => 'active',
        ]);

        return ApiResponse::created($employee->toArray());
    }

    #[ApiEndpoint(
        summary: 'Get employee details',
        description: 'Returns specific employee details by ID.',
        tags: ['HRM'],
        security: ['bearerAuth']
    )]
    public function show(Request $request): JsonResponse
    {
        $id = (int) $request->routeParam('id', 0);
        $employee = Employee::find($id);

        if (!$employee) {
            return ApiResponse::notFound("Employee [{$id}] not found.");
        }

        return ApiResponse::success($employee->toArray());
    }
}
