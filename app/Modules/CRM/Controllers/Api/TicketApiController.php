<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/CRM/Controllers/Api/TicketApiController.php
| Module    : CRM
| Namespace : NetFusion\Modules\CRM\Controllers\Api
| Purpose   : REST API Controller for Helpdesk support tickets and replies.
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
use NetFusion\Modules\CRM\Services\TicketService;

class TicketApiController
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    #[ApiEndpoint(
        summary: 'List support tickets',
        description: 'Returns list of tickets with status and SLA indicators.',
        tags: ['Helpdesk'],
        security: ['bearerAuth']
    )]
    public function index(Request $request): Response
    {
        $limit = (int)($request->input('limit', 20));
        $offset = (int)($request->input('offset', 0));
        $tickets = $this->ticketService->listTickets($limit, $offset);

        $data = array_map(fn($t) => $t->toArray(), $tickets);
        return ApiResponse::success($data, 200);
    }

    #[ApiEndpoint(
        summary: 'Create support ticket',
        description: 'Creates a customer ticket and calculates dynamic SLA due date.',
        tags: ['Helpdesk'],
        security: ['bearerAuth']
    )]
    public function store(Request $request): Response
    {
        $body = !empty($request->json()) ? $request->json() : $request->all();

        $required = ['customer_name', 'customer_email', 'subject', 'description'];
        foreach ($required as $f) {
            if (empty($body[$f])) {
                return ApiResponse::error('Validation failed.', 422, [$f => "Field '{$f}' is required."]);
            }
        }

        try {
            $ticket = $this->ticketService->createTicket($body);
            return ApiResponse::created($ticket->toArray(), 'Ticket created successfully.');
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    #[ApiEndpoint(
        summary: 'Get ticket details',
        description: 'Returns single ticket and all reply threads.',
        tags: ['Helpdesk'],
        security: ['bearerAuth']
    )]
    public function show(Request $request, int $id): Response
    {
        $ticket = $this->ticketService->getTicket($id);
        if (!$ticket) {
            return ApiResponse::error('Ticket not found.', 404);
        }

        return ApiResponse::success([
            'ticket'  => $ticket->toArray(),
            'replies' => array_map(fn($r) => $r->toArray(), $ticket->replies()),
        ]);
    }

    #[ApiEndpoint(
        summary: 'Reply to support ticket',
        description: 'Appends a response to the ticket conversation.',
        tags: ['Helpdesk'],
        security: ['bearerAuth']
    )]
    public function reply(Request $request, int $id): Response
    {
        $body = !empty($request->json()) ? $request->json() : $request->all();

        if (empty($body['message'])) {
            return ApiResponse::error('Validation failed.', 422, ['message' => 'Reply message is required.']);
        }

        try {
            $senderType = $body['sender_type'] ?? 'agent';
            $userId = isset($body['user_id']) ? (int)$body['user_id'] : null;

            $reply = $this->ticketService->addReply($id, (string)$body['message'], $senderType, $userId);
            return ApiResponse::created($reply->toArray(), 'Reply posted successfully.');
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    #[ApiEndpoint(
        summary: 'Resolve ticket',
        description: 'Marks ticket as resolved and timestamps resolution.',
        tags: ['Helpdesk'],
        security: ['bearerAuth']
    )]
    public function resolve(Request $request, int $id): Response
    {
        try {
            $ticket = $this->ticketService->resolveTicket($id);
            return ApiResponse::success($ticket->toArray(), 200);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
