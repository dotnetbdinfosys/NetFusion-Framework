<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/CRM/Controllers/CrmDashboardController.php
| Module    : CRM
| Namespace : NetFusion\Modules\CRM\Controllers
| Purpose   : Web dashboard controller for CRM pipeline and Helpdesk overview.
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

namespace NetFusion\Modules\CRM\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Modules\CRM\Models\Deal;
use NetFusion\Modules\CRM\Models\Lead;
use NetFusion\Modules\CRM\Models\Ticket;
use NetFusion\Modules\CRM\Services\CrmService;

class CrmDashboardController
{
    public function __construct(
        protected CrmService $crmService
    ) {}

    public function index(Request $request): Response
    {
        $leadsCount = Lead::count();
        $dealsCount = Deal::count();
        $openTicketsCount = Ticket::where('status', '=', 'open')->count();
        $pipeline = $this->crmService->getPipelineSummary();
        $recentTickets = Ticket::query()->orderBy('id', 'DESC')->limit(5)->get();

        $data = [
            'leadsCount'       => $leadsCount,
            'dealsCount'       => $dealsCount,
            'openTicketsCount' => $openTicketsCount,
            'pipeline'         => $pipeline,
            'recentTickets'    => $recentTickets,
        ];

        return Response::html((string) view('crm::dashboard', $data));
    }
}
