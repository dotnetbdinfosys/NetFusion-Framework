<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/CRM/Models/Lead.php
| Module    : CRM
| Namespace : NetFusion\Modules\CRM\Models
| Purpose   : Lead Active Record model
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

class Lead extends Model
{
    protected string $table = 'crm_leads';

    protected array $fillable = [
        'tenant_id',
        'name',
        'company',
        'email',
        'phone',
        'status',
        'source',
        'assigned_to',
        'created_at',
        'updated_at',
    ];

    public function deals(): array
    {
        $rows = Deal::where('lead_id', '=', $this->id)->get();
        return array_map(fn($r) => Deal::newFromBuilder($r), $rows);
    }
}
