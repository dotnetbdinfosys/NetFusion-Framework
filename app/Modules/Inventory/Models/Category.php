<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Inventory/Models/Category.php
| Module    : Modules\Inventory
| Namespace : NetFusion\Modules\Inventory\Models
| Purpose   : Category model grouping products.
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
 * Class Category
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $created_at
 * @property string|null $updated_at
 *
 * @package NetFusion\Modules\Inventory\Models
 */
class Category extends Model
{
    protected string $table = 'inv_categories';

    protected array $fillable = [
        'name',
        'code',
        'created_at',
        'updated_at',
    ];

    public function products(): array
    {
        return Product::where('category_id', '=', $this->id)->get();
    }
}
