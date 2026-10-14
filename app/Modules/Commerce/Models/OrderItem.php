<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Commerce/Models/OrderItem.php
| Module    : Commerce
| Namespace : NetFusion\Modules\Commerce\Models
| Purpose   : OrderItem Active Record model
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

namespace NetFusion\Modules\Commerce\Models;

use NetFusion\Core\Database\Model;

class OrderItem extends Model
{
    protected string $table = 'pos_order_items';

    protected array $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'subtotal',
        'created_at',
        'updated_at',
    ];

    public function order(): ?Order
    {
        $row = Order::where('id', '=', $this->order_id)->first();
        return $row ? Order::newFromBuilder($row) : null;
    }
}
