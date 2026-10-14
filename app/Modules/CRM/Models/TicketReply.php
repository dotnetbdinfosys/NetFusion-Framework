<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/CRM/Models/TicketReply.php
| Module    : CRM
| Namespace : NetFusion\Modules\CRM\Models
| Purpose   : Support Ticket Reply Active Record model
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

class TicketReply extends Model
{
    protected string $table = 'crm_ticket_replies';

    protected array $fillable = [
        'ticket_id',
        'user_id',
        'sender_type',
        'message',
        'created_at',
        'updated_at',
    ];

    public function ticket(): ?Ticket
    {
        $row = Ticket::where('id', '=', $this->ticket_id)->first();
        return $row ? Ticket::newFromBuilder($row) : null;
    }
}
