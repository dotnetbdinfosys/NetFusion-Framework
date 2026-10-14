<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Inventory/Services/StockService.php
| Module    : Inventory
| Namespace : NetFusion\Modules\Inventory\Services
| Purpose   : Inventory stock service managing receipts, issues, transfers, and valuation.
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

namespace NetFusion\Modules\Inventory\Services;

use InvalidArgumentException;
use NetFusion\Core\Application;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Modules\Inventory\Models\Product;
use NetFusion\Modules\Inventory\Models\StockMovement;
use NetFusion\Modules\Inventory\Models\WarehouseStock;

/**
 * Class StockService
 *
 * Implements atomic inventory operations: receiving goods, sales dispatches, inter-warehouse transfers,
 * and stock valuation.
 *
 * @package NetFusion\Modules\Inventory\Services
 */
class StockService
{
    public function __construct(protected Application $app)
    {
    }

    /**
     * Receive goods into a warehouse (Inflow / GRN).
     */
    public function receiveStock(
        int $productId,
        int $warehouseId,
        float $quantity,
        float $unitCost = 0.0,
        ?string $reference = null
    ): StockMovement {
        if ($quantity <= 0.0) {
            throw new InvalidArgumentException('Received quantity must be greater than zero.');
        }

        $db = $this->app->make(DatabaseManager::class)->connection();
        $db->beginTransaction();

        try {
            // Update or create warehouse stock record
            $stockRow = WarehouseStock::where('warehouse_id', '=', $warehouseId)
                ->where('product_id', '=', $productId)
                ->first();

            if ($stockRow) {
                $stock = WarehouseStock::newFromBuilder($stockRow);
                $stock->quantity = ((float) $stock->quantity) + $quantity;
                $stock->save();
            } else {
                WarehouseStock::create([
                    'warehouse_id'      => $warehouseId,
                    'product_id'        => $productId,
                    'quantity'          => $quantity,
                    'reserved_quantity' => 0.0,
                    'created_at'        => date('Y-m-d H:i:s'),
                ]);
            }

            // Create movement record
            $movement = StockMovement::create([
                'movement_number'   => 'MOV-IN-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5)),
                'product_id'        => $productId,
                'from_warehouse_id' => null,
                'to_warehouse_id'   => $warehouseId,
                'type'              => StockMovement::TYPE_IN,
                'quantity'          => $quantity,
                'unit_cost'         => $unitCost,
                'reference'         => $reference,
                'created_at'        => date('Y-m-d H:i:s'),
            ]);

            $db->commit();
            return $movement;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Issue goods from a warehouse (Outflow / Sales Dispatch).
     */
    public function issueStock(
        int $productId,
        int $warehouseId,
        float $quantity,
        ?string $reference = null
    ): StockMovement {
        if ($quantity <= 0.0) {
            throw new InvalidArgumentException('Issued quantity must be greater than zero.');
        }

        $db = $this->app->make(DatabaseManager::class)->connection();
        $db->beginTransaction();

        try {
            $stockRow = WarehouseStock::where('warehouse_id', '=', $warehouseId)
                ->where('product_id', '=', $productId)
                ->first();

            $currentQty = $stockRow ? (float) $stockRow['quantity'] : 0.0;
            if ($currentQty < $quantity) {
                throw new InvalidArgumentException(
                    sprintf('Insufficient stock: Available (%.2f), Requested (%.2f).', $currentQty, $quantity)
                );
            }

            $stock = WarehouseStock::newFromBuilder($stockRow);
            $stock->quantity = $currentQty - $quantity;
            $stock->save();

            $product = Product::find($productId);
            $costPrice = $product ? (float) $product->cost_price : 0.0;

            $movement = StockMovement::create([
                'movement_number'   => 'MOV-OUT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5)),
                'product_id'        => $productId,
                'from_warehouse_id' => $warehouseId,
                'to_warehouse_id'   => null,
                'type'              => StockMovement::TYPE_OUT,
                'quantity'          => $quantity,
                'unit_cost'         => $costPrice,
                'reference'         => $reference,
                'created_at'        => date('Y-m-d H:i:s'),
            ]);

            $db->commit();

            // Trigger low-stock alert via webhook if applicable
            if ($product && $product->isLowStock()) {
                if (function_exists('webhook')) {
                    webhook('inventory.low_stock', [
                        'product_id'   => $product->id,
                        'sku'          => $product->sku,
                        'name'         => $product->name,
                        'current_stock'=> $product->totalQuantity(),
                        'threshold'    => (float) $product->min_stock_alert,
                    ]);
                }
            }

            return $movement;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Transfer stock between two warehouses.
     */
    public function transferStock(
        int $productId,
        int $fromWarehouseId,
        int $toWarehouseId,
        float $quantity,
        ?string $reference = null
    ): StockMovement {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new InvalidArgumentException('Source and destination warehouses must be different.');
        }
        if ($quantity <= 0.0) {
            throw new InvalidArgumentException('Transfer quantity must be greater than zero.');
        }

        $db = $this->app->make(DatabaseManager::class)->connection();
        $db->beginTransaction();

        try {
            // Check source stock
            $sourceRow = WarehouseStock::where('warehouse_id', '=', $fromWarehouseId)
                ->where('product_id', '=', $productId)
                ->first();

            $avail = $sourceRow ? (float) $sourceRow['quantity'] : 0.0;
            if ($avail < $quantity) {
                throw new InvalidArgumentException(sprintf('Insufficient stock at source warehouse: Available (%.2f).', $avail));
            }

            $sourceStock = WarehouseStock::newFromBuilder($sourceRow);
            $sourceStock->quantity = $avail - $quantity;
            $sourceStock->save();

            // Increase destination stock
            $destRow = WarehouseStock::where('warehouse_id', '=', $toWarehouseId)
                ->where('product_id', '=', $productId)
                ->first();

            if ($destRow) {
                $destStock = WarehouseStock::newFromBuilder($destRow);
                $destStock->quantity = ((float) $destStock->quantity) + $quantity;
                $destStock->save();
            } else {
                WarehouseStock::create([
                    'warehouse_id'      => $toWarehouseId,
                    'product_id'        => $productId,
                    'quantity'          => $quantity,
                    'reserved_quantity' => 0.0,
                    'created_at'        => date('Y-m-d H:i:s'),
                ]);
            }

            $movement = StockMovement::create([
                'movement_number'   => 'MOV-TRF-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5)),
                'product_id'        => $productId,
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id'   => $toWarehouseId,
                'type'              => StockMovement::TYPE_TRANSFER,
                'quantity'          => $quantity,
                'unit_cost'         => 0.0,
                'reference'         => $reference,
                'created_at'        => date('Y-m-d H:i:s'),
            ]);

            $db->commit();
            return $movement;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Compute total inventory valuation across all products.
     */
    public function getTotalValuation(): float
    {
        $db = $this->app->make(DatabaseManager::class)->connection();
        $rows = $db->select("
            SELECT p.cost_price, SUM(s.quantity) as total_qty
            FROM inv_products p
            JOIN inv_warehouse_stocks s ON p.id = s.product_id
            GROUP BY p.id, p.cost_price
        ");

        $totalVal = 0.0;
        foreach ($rows as $r) {
            $totalVal += ((float) $r['cost_price']) * ((float) $r['total_qty']);
        }

        return round($totalVal, 2);
    }

    /**
     * Get list of products with low stock.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLowStockProducts(): array
    {
        $products = Product::all();
        $lowStock = [];

        foreach ($products as $p) {
            if ($p->isLowStock()) {
                $lowStock[] = [
                    'id'           => $p->id,
                    'sku'          => $p->sku,
                    'name'         => $p->name,
                    'total_stock'  => $p->totalQuantity(),
                    'alert_level'  => (float) $p->min_stock_alert,
                ];
            }
        }

        return $lowStock;
    }
}
