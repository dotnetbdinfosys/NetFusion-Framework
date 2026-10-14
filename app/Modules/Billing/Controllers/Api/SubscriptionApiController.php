<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Controllers/Api/SubscriptionApiController.php
| Module    : Modules\Billing
| Namespace : NetFusion\Modules\Billing\Controllers\Api
| Purpose   : RESTful API controller with ApiResource transformers and OpenAPI Attributes.
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

namespace NetFusion\Modules\Billing\Controllers\Api;

use NetFusion\Core\Http\ApiResourceCollection;
use NetFusion\Core\Http\ApiResponse;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\OpenApi\Attributes\ApiEndpoint;
use NetFusion\Core\OpenApi\Attributes\ApiParam;
use NetFusion\Core\OpenApi\Attributes\ApiResponseDoc;
use NetFusion\Modules\Billing\Events\InvoicePaid;
use NetFusion\Modules\Billing\Events\SubscriptionCreated;
use NetFusion\Modules\Billing\Http\Resources\PlanResource;
use NetFusion\Modules\Billing\Http\Resources\SubscriptionResource;
use NetFusion\Modules\Billing\Models\Invoice;
use NetFusion\Modules\Billing\Models\Plan;
use NetFusion\Modules\Billing\Models\Subscription;

/**
 * NetFusion Subscription API Controller
 *
 * Exposes REST endpoints for managing multi-tenant subscriptions and invoice settlement.
 * All responses use the standardized ApiResponse envelope with ApiResource transformers.
 * OpenAPI Attributes provide auto-documentation at GET /api/docs.
 *
 * @package NetFusion\Modules\Billing\Controllers\Api
 * @version 1.0.0
 */
class SubscriptionApiController
{
    /**
     * List all available subscription plans.
     *
     * GET /api/billing/plans
     */
    #[ApiEndpoint(
        summary: 'List all subscription plans',
        description: 'Returns all active SaaS subscription plans available for multi-tenant subscriptions.',
        tags: ['Billing'],
    )]
    #[ApiResponseDoc(200, description: 'Returns list of active plans', schema: 'PlanResource')]
    #[ApiResponseDoc(500, description: 'Internal server error')]
    public function plans(): Response
    {
        $plans = Plan::getActive();

        $collection = new ApiResourceCollection($plans, PlanResource::class);

        return ApiResponse::success($collection, meta: ['count' => count($plans)]);
    }

    /**
     * Subscribe a tenant to a plan.
     *
     * POST /api/billing/subscribe
     */
    #[ApiEndpoint(
        summary: 'Create a new subscription',
        description: 'Subscribes a tenant to a billing plan. Creates an initial pending invoice and fires SubscriptionCreated event.',
        tags: ['Billing'],
        security: ['bearerAuth'],
    )]
    #[ApiParam(name: 'tenant_id', in: 'query', type: 'string', required: true, description: 'Tenant identifier', example: 'tenant_acme')]
    #[ApiParam(name: 'plan_slug', in: 'query', type: 'string', required: true, description: 'Plan slug to subscribe to', example: 'professional')]
    #[ApiResponseDoc(201, description: 'Subscription created successfully', schema: 'SubscriptionResource')]
    #[ApiResponseDoc(422, description: 'Validation error: tenant_id or plan_slug missing / plan not found')]
    #[ApiResponseDoc(500, description: 'Internal server error')]
    public function subscribe(Request $request): Response
    {
        $tenantId = $request->input('tenant_id');
        $planSlug = $request->input('plan_slug');

        if (empty($tenantId) || empty($planSlug)) {
            return ApiResponse::error('tenant_id and plan_slug are required.', 422);
        }

        $plan = Plan::findBySlug($planSlug);

        if (!$plan) {
            return ApiResponse::error("Plan [{$planSlug}] not found.", 422);
        }

        $trialEnd      = date('Y-m-d H:i:s', strtotime('+14 days'));
        $periodEnd     = date('Y-m-d H:i:s', strtotime('+30 days'));
        $now           = date('Y-m-d H:i:s');
        $invoiceNumber = 'INV-' . strtoupper(bin2hex(random_bytes(4)));

        // Create subscription
        $subscription = new Subscription();
        $subscription->tenant_id          = $tenantId;
        $subscription->plan_id            = (int) $plan->id;
        $subscription->status             = 'trialing';
        $subscription->trial_ends_at      = $trialEnd;
        $subscription->current_period_end = $periodEnd;
        $subscription->created_at         = $now;
        $subscription->updated_at         = $now;
        $subscription->save();

        // Create initial invoice
        $invoice = new Invoice();
        $invoice->tenant_id       = $tenantId;
        $invoice->subscription_id = (int) $subscription->id;
        $invoice->invoice_number  = $invoiceNumber;
        $invoice->amount          = (float) $plan->price;
        $invoice->currency        = $plan->currency ?? 'USD';
        $invoice->status          = 'pending';
        $invoice->due_date        = $periodEnd;
        $invoice->created_at      = $now;
        $invoice->updated_at      = $now;
        $invoice->save();

        // Fire domain event
        dispatch(new SubscriptionCreated($subscription, $plan, $tenantId));

        $subResource = new SubscriptionResource($subscription);

        return ApiResponse::created(
            data: [
                'subscription' => $subResource->resolve(),
                'invoice'      => [
                    'id'             => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount'         => (float) $invoice->amount,
                    'currency'       => $invoice->currency,
                    'status'         => $invoice->status,
                ],
            ],
            message: 'Subscription created in trialing status.',
            links: ['invoice' => '/api/billing/invoices']
        );
    }

    /**
     * List all invoices for the current tenant.
     *
     * GET /api/billing/invoices
     */
    #[ApiEndpoint(
        summary: 'List tenant invoices',
        description: 'Returns all invoices for the given tenant. Filter by status using ?status=pending.',
        tags: ['Billing'],
        security: ['bearerAuth'],
    )]
    #[ApiParam(name: 'tenant_id', in: 'query', type: 'string', required: true, description: 'Tenant identifier', example: 'tenant_acme')]
    #[ApiParam(name: 'status', in: 'query', type: 'string', required: false, description: 'Filter by status (pending, paid)', example: 'pending')]
    #[ApiResponseDoc(200, description: 'Returns list of invoices')]
    #[ApiResponseDoc(422, description: 'Validation error: tenant_id missing')]
    public function invoices(Request $request): Response
    {
        $tenantId = $request->query('tenant_id') ?? $request->input('tenant_id');

        if (empty($tenantId)) {
            return ApiResponse::error('tenant_id is required.', 422);
        }

        $invoices = Invoice::forTenant($tenantId);

        $data = array_map(fn(Invoice $inv): array => [
            'id'             => $inv->id,
            'invoice_number' => $inv->invoice_number,
            'amount'         => (float) $inv->amount,
            'currency'       => $inv->currency,
            'status'         => $inv->status,
            'due_date'       => $inv->due_date,
            'paid_at'        => $inv->paid_at ?? null,
        ], $invoices);

        return ApiResponse::success($data, meta: ['count' => count($data)]);
    }

    /**
     * Pay an invoice by ID.
     *
     * POST /api/billing/invoices/{id}/pay
     */
    #[ApiEndpoint(
        summary: 'Pay an invoice',
        description: 'Marks the specified invoice as paid, updates the linked subscription to active, and fires InvoicePaid event.',
        tags: ['Billing'],
        security: ['bearerAuth'],
    )]
    #[ApiParam(name: 'id', in: 'path', type: 'integer', required: true, description: 'Invoice ID', example: 1)]
    #[ApiResponseDoc(200, description: 'Invoice paid successfully')]
    #[ApiResponseDoc(404, description: 'Invoice not found')]
    #[ApiResponseDoc(409, description: 'Invoice is already paid')]
    public function pay(Request $request): Response
    {
        $invoiceId = (int) $request->routeParam('id', 0);
        $invoice   = Invoice::find($invoiceId);

        if (!$invoice) {
            return ApiResponse::error("Invoice #{$invoiceId} not found.", 404);
        }

        if ($invoice->status === 'paid') {
            return ApiResponse::error("Invoice #{$invoiceId} is already paid.", 409);
        }

        $now = date('Y-m-d H:i:s');

        // Mark invoice as paid
        $invoice->status  = 'paid';
        $invoice->paid_at = $now;
        $invoice->save();

        // Activate subscription
        $subscription = Subscription::find((int) $invoice->subscription_id);
        if ($subscription) {
            $subscription->status     = 'active';
            $subscription->updated_at = $now;
            $subscription->save();
        }

        // Fire domain event
        dispatch(new InvoicePaid($invoice, $invoice->tenant_id));

        return ApiResponse::success([
            'invoice' => [
                'id'             => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status'         => $invoice->status,
                'paid_at'        => $invoice->paid_at,
            ],
            'subscription_status' => $subscription?->status ?? 'active',
        ], meta: ['message' => 'Invoice marked as paid. Subscription activated.']);
    }

}
