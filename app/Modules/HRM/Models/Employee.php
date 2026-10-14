<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/HRM/Models/Employee.php
| Module    : Modules\HRM
| Namespace : NetFusion\Modules\HRM\Models
| Purpose   : Employee active record model equipped with Notifiable trait.
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
use NetFusion\Core\Notifications\Notifiable;

/**
 * NetFusion HRM Employee Model
 *
 * @property int    $id
 * @property string $tenant_id
 * @property int    $department_id
 * @property string $employee_code
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $designation
 * @property float  $basic_salary
 * @property string $joined_date
 * @property string $status
 *
 * @package NetFusion\Modules\HRM\Models
 * @version 1.0.0
 */
class Employee extends Model
{
    use Notifiable;

    protected string $table = 'hrm_employees';

    protected array $fillable = [
        'tenant_id',
        'department_id',
        'employee_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'designation',
        'basic_salary',
        'joined_date',
        'status',
    ];

    /**
     * Get full name attribute.
     */
    public function getFullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get associated department.
     */
    public function department(): ?Department
    {
        return Department::find($this->department_id);
    }
}
