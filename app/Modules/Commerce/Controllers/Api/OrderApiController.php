<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Commerce/Controllers/Api/OrderApiController.php
| Module    : Commerce
| Namespace : NetFusion\Modules\Commerce\Controllers\Api
| Purpose   : REST API Controller for E-Commerce and POS orders.
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

namespace NetFusion\Modules\Commerce\Controllers\Api;

use InvalidArgumentException;
use NetFusion\Core\Http\ApiResponse;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\OpenApi\Attributes\ApiEndpoint;
use NetFusion\Modules\Commerce\Services\CommerceService;

class OrderApiController
{
    public function __construct(
        protected CommerceService $commerceService
    ) {}

    #[ApiEndpoint(
        summary: 'List orders',
        description: 'Returns a list of customer and POS orders.',
        tags: ['Commerce'],
        security: ['bearerAuth']
    )]
    public function index(Request $request): Response
    {
        $limit = (int)($request->input('limit', 20));
        $offset = (int)($request->input('offset', 0));
        $orders = $this->commerceService->listOrders($limit, $offset);

        $data = array_map(function ($order) {
            return [
                'id'             => $order->id,
                'order_number'   => $order->order_number,
                'total_amount'   => (float)$order->total_amount,
                'paid_amount'    => (float)$order->paid_amount,
                'payment_status' => $order->payment_status,
                'order_status'   => $order->order_status,
                'type'           => $order->type,
                'created_at'     => $order->created_at,
            ];
        }, $orders);

        return ApiResponse::success($data, 200);
    }

    #[ApiEndpoint(
        summary: 'Create an order',
        description: 'Creates a sales order with multiple line items, optional payment and stock decrement.',
        tags: ['Commerce'],
        security: ['bearerAuth']
    )]
    public function store(Request $request): Response
    {
        $body = !empty($request->json()) ? $request->json() : $request->all();

        $items = $body['items'] ?? [];
        if (empty($items) || !is_array($items)) {
            return ApiResponse::error('Validation failed.', 422, ['items' => 'Order must include at least one item.']);
        }

        try {
            $orderData = [
                'tenant_id'       => $body['tenant_id'] ?? null,
                'order_number'    => $body['order_number'] ?? null,
                'customer_id'     => $body['customer_id'] ?? null,
                'discount_amount' => $body['discount_amount'] ?? 0.0,
                'tax_amount'      => $body['tax_amount'] ?? 0.0,
                'type'            => $body['type'] ?? 'web',
            ];

            $paymentData = $body['payment'] ?? null;
            $warehouseId = isset($body['warehouse_id']) ? (int)$body['warehouse_id'] : null;

            $order = $this->commerceService->createOrder($orderData, $items, $paymentData, $warehouseId);

            return ApiResponse::created([
                'id'             => $order->id,
                'order_number'   => $order->order_number,
                'total_amount'   => (float)$order->total_amount,
                'paid_amount'    => (float)$order->paid_amount,
                'payment_status' => $order->payment_status,
                'order_status'   => $order->order_status,
            ], 'Order created successfully.');
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    #[ApiEndpoint(
        summary: 'Get order details',
        description: 'Returns single order with its items and payments.',
        tags: ['Commerce'],
        security: ['bearerAuth']
    )]
    public function show(Request $request, int $id): Response
    {
        $order = $this->commerceService->getOrder($id);
        if (!$order) {
            return ApiResponse::error('Order not found.', 404);
        }

        return ApiResponse::success([
            'order'    => $order->toArray(),
            'items'    => array_map(fn($i) => $i->toArray(), $order->items()),
            'payments' => array_map(fn($p) => $p->toArray(), $order->payments()),
        ]);
    }
}
