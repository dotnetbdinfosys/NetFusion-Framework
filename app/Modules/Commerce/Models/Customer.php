<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Commerce/Models/Customer.php
| Module    : Commerce
| Namespace : NetFusion\Modules\Commerce\Models
| Purpose   : Customer Active Record model
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

class Customer extends Model
{
    protected string $table = 'pos_customers';

    protected array $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'address',
        'loyalty_points',
        'balance',
        'status',
        'created_at',
        'updated_at',
    ];

    public function orders(): array
    {
        return Order::where('customer_id', '=', $this->id)->get();
    }
}
