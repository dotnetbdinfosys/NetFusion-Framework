<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/HRM/Models/Payroll.php
| Module    : Modules\HRM
| Namespace : NetFusion\Modules\HRM\Models
| Purpose   : Payroll active record model managing salary generation & disbursement.
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

namespace NetFusion\Modules\HRM\Models;

use NetFusion\Core\Database\Model;

/**
 * NetFusion HRM Payroll Model
 *
 * @property int    $id
 * @property string $tenant_id
 * @property int    $employee_id
 * @property int    $month
 * @property int    $year
 * @property float  $basic_salary
 * @property float  $allowances
 * @property float  $deductions
 * @property float  $net_salary
 * @property string $status
 * @property string $paid_at
 *
 * @package NetFusion\Modules\HRM\Models
 * @version 1.0.0
 */
class Payroll extends Model
{
    protected string $table = 'hrm_payrolls';

    protected array $fillable = [
        'tenant_id',
        'employee_id',
        'month',
        'year',
        'basic_salary',
        'allowances',
        'deductions',
        'net_salary',
        'status',
        'paid_at',
    ];

    /**
     * Associated employee.
     */
    public function employee(): ?Employee
    {
        return Employee::find($this->employee_id);
    }

    /**
     * Mark payroll as disbursed.
     */
    public function markAsPaid(): bool
    {
        $this->status  = 'paid';
        $this->paid_at = date('Y-m-d H:i:s');
        return $this->save();
    }
}
