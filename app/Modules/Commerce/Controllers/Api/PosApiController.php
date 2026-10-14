<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Commerce/Controllers/Api/PosApiController.php
| Module    : Commerce
| Namespace : NetFusion\Modules\Commerce\Controllers\Api
| Purpose   : High-throughput POS checkout and cash desk transaction API.
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

use NetFusion\Core\Http\ApiResponse;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\OpenApi\Attributes\ApiEndpoint;
use NetFusion\Modules\Commerce\Services\CommerceService;

class PosApiController
{
    public function __construct(
        protected CommerceService $commerceService
    ) {}

    #[ApiEndpoint(
        summary: 'POS instant checkout',
        description: 'Instant checkout for POS cashiers with automatic inventory deduction and receipt generation.',
        tags: ['Commerce'],
        security: ['bearerAuth']
    )]
    public function checkout(Request $request): Response
    {
        $body = !empty($request->json()) ? $request->json() : $request->all();

        $items = $body['items'] ?? [];
        if (empty($items) || !is_array($items)) {
            return ApiResponse::error('Validation failed.', 422, ['items' => 'Items are required for POS checkout.']);
        }

        try {
            $paidAmount = (float)($body['paid_amount'] ?? 0.0);
            $paymentMethod = (string)($body['payment_method'] ?? 'cash');

            $orderData = [
                'type'            => 'pos',
                'customer_id'     => $body['customer_id'] ?? null,
                'discount_amount' => (float)($body['discount_amount'] ?? 0.0),
                'tax_amount'      => (float)($body['tax_amount'] ?? 0.0),
            ];

            $paymentData = [
                'amount'                => $paidAmount,
                'payment_method'        => $paymentMethod,
                'transaction_reference' => $body['reference'] ?? 'POS-' . time(),
            ];

            $warehouseId = isset($body['warehouse_id']) ? (int)$body['warehouse_id'] : 1;

            $order = $this->commerceService->createOrder($orderData, $items, $paymentData, $warehouseId);

            return ApiResponse::created([
                'order_id'       => $order->id,
                'order_number'   => $order->order_number,
                'total_amount'   => (float)$order->total_amount,
                'paid_amount'    => (float)$order->paid_amount,
                'change_amount'  => max(0.0, $paidAmount - (float)$order->total_amount),
                'payment_status' => $order->payment_status,
                'order_status'   => $order->order_status,
            ], 'POS Checkout completed successfully.');
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
