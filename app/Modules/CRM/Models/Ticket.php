<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/CRM/Models/Ticket.php
| Module    : CRM
| Namespace : NetFusion\Modules\CRM\Models
| Purpose   : Customer Support Ticket Active Record model
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

class Ticket extends Model
{
    protected string $table = 'crm_tickets';

    protected array $fillable = [
        'tenant_id',
        'ticket_number',
        'customer_name',
        'customer_email',
        'subject',
        'description',
        'priority',
        'status',
        'assigned_to',
        'sla_due_at',
        'resolved_at',
        'created_at',
        'updated_at',
    ];

    public function replies(): array
    {
        $rows = TicketReply::where('ticket_id', '=', $this->id)->get();
        return array_map(fn($r) => TicketReply::newFromBuilder($r), $rows);
    }
}
