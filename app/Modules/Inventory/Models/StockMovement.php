<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Inventory/Models/StockMovement.php
| Module    : Modules\Inventory
| Namespace : NetFusion\Modules\Inventory\Models
| Purpose   : Stock movement audit ledger model (Inflow, Outflow, Transfer).
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
 * Class StockMovement
 *
 * @property int $id
 * @property string $movement_number
 * @property int $product_id
 * @property int|null $from_warehouse_id
 * @property int|null $to_warehouse_id
 * @property string $type
 * @property float $quantity
 * @property float $unit_cost
 * @property string|null $reference
 * @property string $created_at
 * @property string|null $updated_at
 *
 * @package NetFusion\Modules\Inventory\Models
 */
class StockMovement extends Model
{
    protected string $table = 'inv_stock_movements';

    protected array $fillable = [
        'movement_number',
        'product_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'type',
        'quantity',
        'unit_cost',
        'reference',
        'created_at',
        'updated_at',
    ];

    public const TYPE_IN       = 'in';
    public const TYPE_OUT      = 'out';
    public const TYPE_TRANSFER = 'transfer';

    public function product(): ?Product
    {
        return Product::find($this->product_id);
    }
}
