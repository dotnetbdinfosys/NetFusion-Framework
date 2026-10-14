<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Commerce/Services/CommerceService.php
| Module    : Commerce
| Namespace : NetFusion\Modules\Commerce\Services
| Purpose   : Core business service for POS and E-Commerce sales, inventory sync and accounting ledgers.
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

namespace NetFusion\Modules\Commerce\Services;

use NetFusion\Core\Application;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Modules\Accounting\Models\Account;
use NetFusion\Modules\Accounting\Services\LedgerService;
use NetFusion\Modules\Commerce\Models\Customer;
use NetFusion\Modules\Commerce\Models\Order;
use NetFusion\Modules\Commerce\Models\OrderItem;
use NetFusion\Modules\Commerce\Models\Payment;
use NetFusion\Modules\Inventory\Services\StockService;
use RuntimeException;

class CommerceService
{
    public function __construct(
        protected Application $app
    ) {}

    protected function getDb(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection();
    }

    public function createCustomer(array $data): Customer
    {
        $db = $this->getDb();
        $now = date('Y-m-d H:i:s');
        $id = $db->insert("
            INSERT INTO pos_customers (tenant_id, name, email, phone, address, loyalty_points, balance, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $data['tenant_id'] ?? null,
            $data['name'],
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['loyalty_points'] ?? 0,
            $data['balance'] ?? 0.0,
            $data['status'] ?? 'active',
            $now,
            $now,
        ]);

        $row = Customer::where('id', '=', (int)$id)->first();
        return Customer::newFromBuilder($row);
    }

    public function createOrder(
        array $orderData,
        array $items,
        ?array $paymentData = null,
        ?int $warehouseId = null
    ): Order {
        if (empty($items)) {
            throw new RuntimeException("Cannot create an order without line items.");
        }

        $db = $this->getDb();
        $now = date('Y-m-d H:i:s');
        $orderNumber = $orderData['order_number'] ?? ('ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))));

        // Calculate totals
        $subtotal = 0.0;
        foreach ($items as $item) {
            $qty = (float)($item['quantity'] ?? 1.0);
            $unitPrice = (float)($item['unit_price'] ?? 0.0);
            $subtotal += ($qty * $unitPrice);
        }

        $discount = (float)($orderData['discount_amount'] ?? 0.0);
        $tax = (float)($orderData['tax_amount'] ?? 0.0);
        $total = max(0.0, ($subtotal - $discount + $tax));
        $customerId = isset($orderData['customer_id']) ? (int)$orderData['customer_id'] : null;
        $type = $orderData['type'] ?? 'pos';

        // Insert Order
        $orderId = (int)$db->insert("
            INSERT INTO pos_orders (tenant_id, order_number, customer_id, subtotal, discount_amount, tax_amount, total_amount, paid_amount, payment_status, order_status, type, warehouse_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0.0, 'unpaid', 'pending', ?, ?, ?, ?)
        ", [
            $orderData['tenant_id'] ?? null,
            $orderNumber,
            $customerId,
            $subtotal,
            $discount,
            $tax,
            $total,
            $type,
            $warehouseId,
            $now,
            $now,
        ]);

        // Insert line items & decrement inventory stock if warehouse provided
        $stockService = null;
        if ($warehouseId !== null && class_exists(StockService::class)) {
            try {
                $stockService = $this->app->make(StockService::class);
            } catch (\Throwable) {
                $stockService = new StockService($this->app);
            }
        }

        foreach ($items as $item) {
            $qty = (float)($item['quantity'] ?? 1.0);
            $unitPrice = (float)($item['unit_price'] ?? 0.0);
            $itemSubtotal = $qty * $unitPrice;
            $productId = isset($item['product_id']) ? (int)$item['product_id'] : null;
            $productName = $item['product_name'] ?? 'Product #' . $productId;

            $db->insert("
                INSERT INTO pos_order_items (order_id, product_id, product_name, quantity, unit_price, subtotal, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $orderId,
                $productId,
                $productName,
                $qty,
                $unitPrice,
                $itemSubtotal,
                $now,
                $now,
            ]);

            // Sync Inventory Outflow
            if ($stockService !== null && $productId !== null) {
                $stockService->issueStock(
                    $productId,
                    $warehouseId,
                    $qty,
                    'Order Sale: ' . $orderNumber
                );
            }
        }

        // Handle Payment if supplied
        $paidAmount = 0.0;
        $paymentStatus = 'unpaid';
        $orderStatus = 'pending';

        if ($paymentData !== null && !empty($paymentData['amount'])) {
            $paidAmount = (float)$paymentData['amount'];
            $method = $paymentData['payment_method'] ?? 'cash';
            $ref = $paymentData['transaction_reference'] ?? null;

            $db->insert("
                INSERT INTO pos_payments (order_id, amount, payment_method, transaction_reference, paid_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [
                $orderId,
                $paidAmount,
                $method,
                $ref,
                $now,
                $now,
                $now,
            ]);

            $paymentStatus = ($paidAmount >= $total) ? 'paid' : 'partial';
            $orderStatus = ($paidAmount >= $total) ? 'completed' : 'pending';

            $db->statement("
                UPDATE pos_orders
                SET paid_amount = ?, payment_status = ?, order_status = ?, updated_at = ?
                WHERE id = ?
            ", [
                $paidAmount,
                $paymentStatus,
                $orderStatus,
                $now,
                $orderId,
            ]);
        }

        // Post Double-Entry Accounting Journal if Accounting module exists
        if (class_exists(LedgerService::class)) {
            $this->postAccountingJournal($orderNumber, $total, $paidAmount);
        }

        // Webhook dispatch
        if (function_exists('webhook')) {
            try {
                webhook()->dispatch('order.created', [
                    'order_id'     => $orderId,
                    'order_number' => $orderNumber,
                    'total_amount' => $total,
                    'type'         => $type,
                ]);

                if ($orderStatus === 'completed') {
                    webhook()->dispatch('order.completed', [
                        'order_id'     => $orderId,
                        'order_number' => $orderNumber,
                        'total_amount' => $total,
                        'paid_amount'  => $paidAmount,
                    ]);
                }
            } catch (\Throwable) {
                // Ignore webhook delivery failures in order creation
            }
        }

        $row = Order::where('id', '=', $orderId)->first();
        return Order::newFromBuilder($row);
    }

    protected function postAccountingJournal(string $orderNumber, float $total, float $paidAmount): void
    {
        try {
            $cashAcc = Account::where('code', '=', '1000')->first();
            $arAcc = Account::where('code', '=', '1100')->first();
            $salesAcc = Account::where('code', '=', '4000')->first();

            if (!$salesAcc) {
                return;
            }

            $items = [];
            if ($paidAmount > 0.0 && $cashAcc) {
                $items[] = [
                    'account_id'  => (int)$cashAcc['id'],
                    'debit'       => $paidAmount,
                    'credit'      => 0.0,
                    'description' => 'Cash received - ' . $orderNumber,
                ];
            }

            $due = $total - $paidAmount;
            if ($due > 0.0 && $arAcc) {
                $items[] = [
                    'account_id'  => (int)$arAcc['id'],
                    'debit'       => $due,
                    'credit'      => 0.0,
                    'description' => 'Receivable due - ' . $orderNumber,
                ];
            }

            $items[] = [
                'account_id'  => (int)$salesAcc['id'],
                'debit'       => 0.0,
                'credit'      => $total,
                'description' => 'Sales Revenue - ' . $orderNumber,
            ];

            $ledger = new LedgerService($this->app);
            $ledger->createEntry([
                'entry_date'  => date('Y-m-d'),
                'reference'   => $orderNumber,
                'description' => 'Sales Order: ' . $orderNumber,
                'status'      => 'posted',
            ], $items);
        } catch (\Throwable) {
            // Accounts might not be seeded or present
        }
    }

    public function recordPayment(int $orderId, float $amount, string $method, ?string $reference = null): Payment
    {
        $db = $this->getDb();
        $now = date('Y-m-d H:i:s');
        $order = Order::where('id', '=', $orderId)->first();
        if (!$order) {
            throw new RuntimeException("Order #{$orderId} not found.");
        }

        $paymentId = (int)$db->insert("
            INSERT INTO pos_payments (order_id, amount, payment_method, transaction_reference, paid_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ", [
            $orderId,
            $amount,
            $method,
            $reference,
            $now,
            $now,
            $now,
        ]);

        $newPaid = (float)$order['paid_amount'] + $amount;
        $total = (float)$order['total_amount'];
        $newPaymentStatus = ($newPaid >= $total) ? 'paid' : 'partial';
        $newOrderStatus = ($newPaid >= $total) ? 'completed' : $order['order_status'];

        $db->statement("
            UPDATE pos_orders
            SET paid_amount = ?, payment_status = ?, order_status = ?, updated_at = ?
            WHERE id = ?
        ", [
            $newPaid,
            $newPaymentStatus,
            $newOrderStatus,
            $now,
            $orderId,
        ]);

        $pRow = Payment::where('id', '=', $paymentId)->first();
        return Payment::newFromBuilder($pRow);
    }

    public function getOrder(int $id): ?Order
    {
        $row = Order::where('id', '=', $id)->first();
        return $row ? Order::newFromBuilder($row) : null;
    }

    public function listOrders(int $limit = 50, int $offset = 0): array
    {
        $rows = Order::query()->limit($limit)->offset($offset)->get();
        return array_map(fn($r) => Order::newFromBuilder($r), $rows);
    }
}
