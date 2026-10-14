<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/CRM/Controllers/Api/LeadApiController.php
| Module    : CRM
| Namespace : NetFusion\Modules\CRM\Controllers\Api
| Purpose   : REST API Controller for CRM leads and deal pipeline.
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

namespace NetFusion\Modules\CRM\Controllers\Api;

use NetFusion\Core\Http\ApiResponse;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\OpenApi\Attributes\ApiEndpoint;
use NetFusion\Modules\CRM\Services\CrmService;

class LeadApiController
{
    public function __construct(
        protected CrmService $crmService
    ) {}

    #[ApiEndpoint(
        summary: 'List leads',
        description: 'Returns list of sales leads.',
        tags: ['CRM'],
        security: ['bearerAuth']
    )]
    public function index(Request $request): Response
    {
        $limit = (int)($request->input('limit', 20));
        $offset = (int)($request->input('offset', 0));
        $leads = $this->crmService->listLeads($limit, $offset);

        $data = array_map(fn($lead) => $lead->toArray(), $leads);
        return ApiResponse::success($data, 200);
    }

    #[ApiEndpoint(
        summary: 'Create a new lead',
        description: 'Captures and creates a new sales lead.',
        tags: ['CRM'],
        security: ['bearerAuth']
    )]
    public function store(Request $request): Response
    {
        $body = !empty($request->json()) ? $request->json() : $request->all();

        if (empty($body['name'])) {
            return ApiResponse::error('Validation failed.', 422, ['name' => 'Lead name is required.']);
        }

        try {
            $lead = $this->crmService->createLead($body);
            return ApiResponse::created($lead->toArray(), 'Lead created successfully.');
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    #[ApiEndpoint(
        summary: 'Update lead status',
        description: 'Updates lead qualification status (new, contacted, qualified, lost).',
        tags: ['CRM'],
        security: ['bearerAuth']
    )]
    public function updateStatus(Request $request, int $id): Response
    {
        $body = !empty($request->json()) ? $request->json() : $request->all();
        $status = $body['status'] ?? 'contacted';

        try {
            $lead = $this->crmService->updateLeadStatus($id, $status);
            return ApiResponse::success($lead->toArray(), 200);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    #[ApiEndpoint(
        summary: 'Create deal from lead',
        description: 'Converts or attaches a deal to a lead.',
        tags: ['CRM'],
        security: ['bearerAuth']
    )]
    public function storeDeal(Request $request): Response
    {
        $body = !empty($request->json()) ? $request->json() : $request->all();

        if (empty($body['title'])) {
            return ApiResponse::error('Validation failed.', 422, ['title' => 'Deal title is required.']);
        }

        try {
            $deal = $this->crmService->createDeal($body);
            return ApiResponse::created($deal->toArray(), 'Deal created successfully.');
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    #[ApiEndpoint(
        summary: 'Sales pipeline overview',
        description: 'Returns aggregated summary of deals across pipeline stages.',
        tags: ['CRM'],
        security: ['bearerAuth']
    )]
    public function pipeline(Request $request): Response
    {
        $summary = $this->crmService->getPipelineSummary();
        return ApiResponse::success($summary, 200);
    }
}
