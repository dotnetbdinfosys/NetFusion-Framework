<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/HRM/Models/Department.php
| Module    : Modules\HRM
| Namespace : NetFusion\Modules\HRM\Models
| Purpose   : Department active record model representing organizational units.
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
 * NetFusion HRM Department Model
 *
 * @property int    $id
 * @property string $tenant_id
 * @property string $name
 * @property string $code
 * @property float  $budget
 * @property string $status
 *
 * @package NetFusion\Modules\HRM\Models
 * @version 1.0.0
 */
class Department extends Model
{
    protected string $table = 'hrm_departments';

    protected array $fillable = [
        'tenant_id',
        'name',
        'code',
        'budget',
        'status',
    ];

    /**
     * Retrieve all employees belonging to this department.
     */
    public function employees(): array
    {
        return Employee::where('department_id', '=', $this->id)->get();
    }
}
