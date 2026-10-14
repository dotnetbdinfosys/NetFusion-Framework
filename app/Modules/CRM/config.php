<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/CRM/config.php
| Module    : CRM
| Namespace : NetFusion\Modules\CRM
| Purpose   : CRM and Customer Support configuration file
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

return [
    'stages' => [
        'lead' => ['new', 'contacted', 'qualified', 'lost'],
        'deal' => ['prospect', 'proposal', 'negotiation', 'won', 'lost'],
    ],
    'sla_hours' => [
        'urgent' => 4,
        'high'   => 12,
        'medium' => 24,
        'low'    => 48,
    ],
    'default_priority' => 'medium',
];
