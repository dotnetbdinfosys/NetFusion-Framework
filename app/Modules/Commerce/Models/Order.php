<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Commerce/Models/Order.php
| Module    : Commerce
| Namespace : NetFusion\Modules\Commerce\Models
| Purpose   : Order Active Record model
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

class Order extends Model
{
    protected string $table = 'pos_orders';

    protected array $fillable = [
        'tenant_id',
        'order_number',
        'customer_id',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'payment_status',
        'order_status',
        'type',
        'warehouse_id',
        'created_at',
        'updated_at',
    ];

    public function items(): array
    {
        $rows = OrderItem::where('order_id', '=', $this->id)->get();
        return array_map(fn($r) => OrderItem::newFromBuilder($r), $rows);
    }

    public function payments(): array
    {
        $rows = Payment::where('order_id', '=', $this->id)->get();
        return array_map(fn($r) => Payment::newFromBuilder($r), $rows);
    }

    public function customer(): ?Customer
    {
        if (empty($this->customer_id)) {
            return null;
        }

        $row = Customer::where('id', '=', $this->customer_id)->first();
        return $row ? Customer::newFromBuilder($row) : null;
    }
}
