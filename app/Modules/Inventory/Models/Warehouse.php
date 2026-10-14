<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Inventory/Models/Warehouse.php
| Module    : Modules\Inventory
| Namespace : NetFusion\Modules\Inventory\Models
| Purpose   : Warehouse model representing physical and virtual storage locations.
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

namespace NetFusion\Modules\Inventory\Models;

use NetFusion\Core\Database\Model;

/**
 * Class Warehouse
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $location
 * @property int $is_active
 * @property string $created_at
 * @property string|null $updated_at
 *
 * @package NetFusion\Modules\Inventory\Models
 */
class Warehouse extends Model
{
    protected string $table = 'inv_warehouses';

    protected array $fillable = [
        'code',
        'name',
        'location',
        'is_active',
        'created_at',
        'updated_at',
    ];

    public function stocks(): array
    {
        return WarehouseStock::where('warehouse_id', '=', $this->id)->get();
    }
}
