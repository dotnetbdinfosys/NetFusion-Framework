<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Inventory/Controllers/Api/StockApiController.php
| Module    : Modules\Inventory
| Namespace : NetFusion\Modules\Inventory\Controllers\Api
| Purpose   : REST API Controller for stock operations (Receive, Issue, Transfer, Valuation).
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

namespace NetFusion\Modules\Inventory\Controllers\Api;

use InvalidArgumentException;
use NetFusion\Core\Http\ApiResponse;
use NetFusion\Core\Http\JsonResponse;
use NetFusion\Core\Http\Request;
use NetFusion\Core\OpenApi\Attributes\ApiEndpoint;
use NetFusion\Modules\Inventory\Services\StockService;

/**
 * Class StockApiController
 *
 * @package NetFusion\Modules\Inventory\Controllers\Api
 */
class StockApiController
{
    public function __construct(
        protected StockService $stockService
    ) {}

    #[ApiEndpoint(
        summary: 'Receive stock into warehouse (GRN)',
        description: 'Records inbound inventory stock and increases warehouse balance.',
        tags: ['Inventory'],
        security: ['bearerAuth']
    )]
    public function receive(Request $request): JsonResponse
    {
        $body = !empty($request->json()) ? $request->json() : $request->all();

        $productId   = (int) ($body['product_id'] ?? 0);
        $warehouseId = (int) ($body['warehouse_id'] ?? 0);
        $quantity    = (float) ($body['quantity'] ?? 0.0);
        $unitCost    = (float) ($body['unit_cost'] ?? 0.0);
        $ref         = (string) ($body['reference'] ?? 'GRN Inbound');

        if ($productId <= 0 || $warehouseId <= 0 || $quantity <= 0.0) {
            return ApiResponse::error('Valid product_id, warehouse_id, and positive quantity are required.', 422);
        }

        try {
            $movement = $this->stockService->receiveStock($productId, $warehouseId, $quantity, $unitCost, $ref);
            return ApiResponse::created($movement->toArray(), 'Stock received into warehouse successfully.');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    #[ApiEndpoint(
        summary: 'Issue stock from warehouse',
        description: 'Deducts stock for customer sales or dispatches.',
        tags: ['Inventory'],
        security: ['bearerAuth']
    )]
    public function issue(Request $request): JsonResponse
    {
        $body = !empty($request->json()) ? $request->json() : $request->all();

        $productId   = (int) ($body['product_id'] ?? 0);
        $warehouseId = (int) ($body['warehouse_id'] ?? 0);
        $quantity    = (float) ($body['quantity'] ?? 0.0);
        $ref         = (string) ($body['reference'] ?? 'Sales Outbound');

        if ($productId <= 0 || $warehouseId <= 0 || $quantity <= 0.0) {
            return ApiResponse::error('Valid product_id, warehouse_id, and positive quantity are required.', 422);
        }

        try {
            $movement = $this->stockService->issueStock($productId, $warehouseId, $quantity, $ref);
            return ApiResponse::success($movement->toArray(), 200, ['message' => 'Stock issued from warehouse successfully.']);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    #[ApiEndpoint(
        summary: 'Transfer stock between warehouses',
        description: 'Moves stock atomically from a source warehouse to a destination warehouse.',
        tags: ['Inventory'],
        security: ['bearerAuth']
    )]
    public function transfer(Request $request): JsonResponse
    {
        $body = !empty($request->json()) ? $request->json() : $request->all();

        $productId       = (int) ($body['product_id'] ?? 0);
        $fromWarehouseId = (int) ($body['from_warehouse_id'] ?? 0);
        $toWarehouseId   = (int) ($body['to_warehouse_id'] ?? 0);
        $quantity        = (float) ($body['quantity'] ?? 0.0);
        $ref             = (string) ($body['reference'] ?? 'Inter-warehouse transfer');

        if ($productId <= 0 || $fromWarehouseId <= 0 || $toWarehouseId <= 0 || $quantity <= 0.0) {
            return ApiResponse::error('Valid product, source/target warehouses, and positive quantity are required.', 422);
        }

        try {
            $movement = $this->stockService->transferStock($productId, $fromWarehouseId, $toWarehouseId, $quantity, $ref);
            return ApiResponse::success($movement->toArray(), 200, ['message' => 'Stock transferred successfully.']);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    #[ApiEndpoint(
        summary: 'Get inventory valuation report',
        description: 'Calculates total asset value of current stock holdings.',
        tags: ['Inventory'],
        security: ['bearerAuth']
    )]
    public function valuation(Request $request): JsonResponse
    {
        $totalValuation = $this->stockService->getTotalValuation();
        $lowStock = $this->stockService->getLowStockProducts();

        return ApiResponse::success([
            'total_valuation'    => $totalValuation,
            'low_stock_products' => $lowStock,
        ]);
    }
}
