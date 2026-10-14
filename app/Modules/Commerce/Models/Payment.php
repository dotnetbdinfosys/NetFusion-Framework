<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Commerce/Models/Payment.php
| Module    : Commerce
| Namespace : NetFusion\Modules\Commerce\Models
| Purpose   : Payment Active Record model
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

class Payment extends Model
{
    protected string $table = 'pos_payments';

    protected array $fillable = [
        'order_id',
        'amount',
        'payment_method',
        'transaction_reference',
        'paid_at',
        'created_at',
        'updated_at',
    ];

    public function order(): ?Order
    {
        $row = Order::where('id', '=', $this->order_id)->first();
        return $row ? Order::newFromBuilder($row) : null;
    }
}
