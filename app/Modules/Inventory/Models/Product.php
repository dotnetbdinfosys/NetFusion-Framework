<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Inventory/Models/Product.php
| Module    : Modules\Inventory
| Namespace : NetFusion\Modules\Inventory\Models
| Purpose   : Product inventory master model.
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
 * Class Product
 *
 * @property int $id
 * @property int|null $category_id
 * @property string $sku
 * @property string $name
 * @property string $unit
 * @property float $cost_price
 * @property float $selling_price
 * @property float $min_stock_alert
 * @property string $created_at
 * @property string|null $updated_at
 *
 * @package NetFusion\Modules\Inventory\Models
 */
class Product extends Model
{
    protected string $table = 'inv_products';

    protected array $fillable = [
        'category_id',
        'sku',
        'name',
        'unit',
        'cost_price',
        'selling_price',
        'min_stock_alert',
        'created_at',
        'updated_at',
    ];

    public function category(): ?Category
    {
        return $this->category_id ? Category::find($this->category_id) : null;
    }

    public function stocks(): array
    {
        return WarehouseStock::where('product_id', '=', $this->id)->get();
    }

    /**
     * Calculate total available stock across all warehouses.
     */
    public function totalQuantity(): float
    {
        $stocks = $this->stocks();
        $total = 0.0;
        foreach ($stocks as $stock) {
            $total += is_array($stock) ? (float) ($stock['quantity'] ?? 0) : (float) $stock->quantity;
        }
        return $total;
    }

    /**
     * Determine if product has fallen below the safety threshold.
     */
    public function isLowStock(): bool
    {
        return $this->totalQuantity() <= (float) $this->min_stock_alert;
    }
}
