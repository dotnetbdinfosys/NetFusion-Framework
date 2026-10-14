<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Inventory/Controllers/InventoryDashboardController.php
| Module    : Modules\Inventory
| Namespace : NetFusion\Modules\Inventory\Controllers
| Purpose   : Web dashboard controller rendering inventory valuation, warehouses, and low-stock alerts.
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

namespace NetFusion\Modules\Inventory\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Modules\Inventory\Models\Product;
use NetFusion\Modules\Inventory\Models\StockMovement;
use NetFusion\Modules\Inventory\Models\Warehouse;
use NetFusion\Modules\Inventory\Services\StockService;

/**
 * Class InventoryDashboardController
 *
 * @package NetFusion\Modules\Inventory\Controllers
 */
class InventoryDashboardController
{
    public function __construct(
        protected StockService $stockService
    ) {}

    public function index(Request $request): Response
    {
        $products = Product::all();
        $warehouses = Warehouse::all();
        $movements = StockMovement::orderBy('id', 'DESC')->limit(10)->get();

        $valuation = $this->stockService->getTotalValuation();
        $lowStock = $this->stockService->getLowStockProducts();

        $viewHtml = (string) view('inventory::dashboard', [
            'products'   => $products,
            'warehouses' => $warehouses,
            'movements'  => $movements,
            'valuation'  => $valuation,
            'lowStock'   => $lowStock,
        ]);

        return new Response($viewHtml);
    }
}
