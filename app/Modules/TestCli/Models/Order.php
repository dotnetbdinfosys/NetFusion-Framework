<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/TestCli/Models/Order.php
| Module    : Modules\TestCli
| Namespace : NetFusion\Modules\TestCli\Models
| Purpose   : Active Record Model representing the orders database table.
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

namespace NetFusion\Modules\TestCli\Models;

use NetFusion\Core\Database\Model;

class Order extends Model
{
    /**
     * The table associated with the model.
     */
    protected string $table = 'orders';

    /**
     * Primary key column.
     */
    protected string $primaryKey = 'id';

    /**
     * Mass assignable attributes.
     *
     * @var string[]
     */
    protected array $fillable = [
        // 'name', 'status', 'amount'
    ];

    /**
     * Hidden attributes excluded from arrays/JSON.
     *
     * @var string[]
     */
    protected array $hidden = [];

    /**
     * Auto-manage timestamps (created_at, updated_at).
     */
    protected bool $timestamps = true;
}