<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Inventory/Models/WarehouseStock.php
| Module    : Modules\Inventory
| Namespace : NetFusion\Modules\Inventory\Models
| Purpose   : Tracks per-warehouse stock balances for products.
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
 * Class WarehouseStock
 *
 * @property int $id
 * @property int $warehouse_id
 * @property int $product_id
 * @property float $quantity
 * @property float $reserved_quantity
 * @property string $created_at
 * @property string|null $updated_at
 *
 * @package NetFusion\Modules\Inventory\Models
 */
class WarehouseStock extends Model
{
    protected string $table = 'inv_warehouse_stocks';

    protected array $fillable = [
        'warehouse_id',
        'product_id',
        'quantity',
        'reserved_quantity',
        'created_at',
        'updated_at',
    ];

    public function product(): ?Product
    {
        return Product::find($this->product_id);
    }

    public function warehouse(): ?Warehouse
    {
        return Warehouse::find($this->warehouse_id);
    }
}
