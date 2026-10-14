<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Inventory/Controllers/Api/ProductApiController.php
| Module    : Modules\Inventory
| Namespace : NetFusion\Modules\Inventory\Controllers\Api
| Purpose   : REST API Controller for Product Catalog management.
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

use NetFusion\Core\Http\ApiResponse;
use NetFusion\Core\Http\JsonResponse;
use NetFusion\Core\Http\Request;
use NetFusion\Core\OpenApi\Attributes\ApiEndpoint;
use NetFusion\Modules\Inventory\Models\Product;

/**
 * Class ProductApiController
 *
 * @package NetFusion\Modules\Inventory\Controllers\Api
 */
class ProductApiController
{
    #[ApiEndpoint(
        summary: 'List all products',
        description: 'Returns product catalog items along with their total stock quantities.',
        tags: ['Inventory'],
        security: ['bearerAuth']
    )]
    public function index(Request $request): JsonResponse
    {
        $products = Product::all();
        $data = [];

        foreach ($products as $p) {
            $arr = $p->toArray();
            $arr['total_stock'] = $p->totalQuantity();
            $arr['is_low_stock'] = $p->isLowStock();
            $data[] = $arr;
        }

        return ApiResponse::success($data);
    }

    #[ApiEndpoint(
        summary: 'Create inventory product',
        description: 'Registers a new SKU in the inventory catalog.',
        tags: ['Inventory'],
        security: ['bearerAuth']
    )]
    public function store(Request $request): JsonResponse
    {
        $body = !empty($request->json()) ? $request->json() : $request->all();

        if (empty($body['sku']) || empty($body['name'])) {
            return ApiResponse::error('Product SKU and name are required.', 422);
        }

        $existing = Product::where('sku', '=', (string) $body['sku'])->first();
        if ($existing) {
            return ApiResponse::error('A product with this SKU already exists.', 409);
        }

        $product = Product::create([
            'category_id'     => isset($body['category_id']) ? (int) $body['category_id'] : null,
            'sku'             => (string) $body['sku'],
            'name'            => (string) $body['name'],
            'unit'            => (string) ($body['unit'] ?? 'pcs'),
            'cost_price'      => (float) ($body['cost_price'] ?? 0.0),
            'selling_price'   => (float) ($body['selling_price'] ?? 0.0),
            'min_stock_alert' => (float) ($body['min_stock_alert'] ?? 10.0),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        return ApiResponse::created($product->toArray(), 'Product registered successfully.');
    }

    #[ApiEndpoint(
        summary: 'Get product details',
        description: 'Returns product details including warehouse stock breakdown.',
        tags: ['Inventory'],
        security: ['bearerAuth']
    )]
    public function show(Request $request): JsonResponse
    {
        $id = (int) ($request->routeParam('id') ?? $request->input('id'));
        $product = Product::find($id);

        if (!$product) {
            return ApiResponse::error('Product not found.', 404);
        }

        $arr = $product->toArray();
        $arr['stocks'] = $product->stocks();
        $arr['total_stock'] = $product->totalQuantity();
        $arr['is_low_stock'] = $product->isLowStock();

        return ApiResponse::success($arr);
    }
}
