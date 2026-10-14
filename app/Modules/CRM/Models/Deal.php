<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/CRM/Models/Deal.php
| Module    : CRM
| Namespace : NetFusion\Modules\CRM\Models
| Purpose   : Deal Active Record model
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

namespace NetFusion\Modules\CRM\Models;

use NetFusion\Core\Database\Model;

class Deal extends Model
{
    protected string $table = 'crm_deals';

    protected array $fillable = [
        'tenant_id',
        'lead_id',
        'title',
        'amount',
        'stage',
        'expected_close_date',
        'created_at',
        'updated_at',
    ];

    public function lead(): ?Lead
    {
        if (empty($this->lead_id)) {
            return null;
        }

        $row = Lead::where('id', '=', $this->lead_id)->first();
        return $row ? Lead::newFromBuilder($row) : null;
    }
}
