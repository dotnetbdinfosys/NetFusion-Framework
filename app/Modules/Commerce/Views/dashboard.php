<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commerce & POS Dashboard — NetFusion Framework</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; padding-bottom: 1rem; margin-bottom: 2rem; }
        .badge { background: #059669; color: #fff; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 600; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: #1e293b; padding: 1.5rem; border-radius: 0.5rem; border: 1px solid #334155; }
        .stat-val { font-size: 2rem; font-weight: 700; color: #38bdf8; margin-top: 0.5rem; }
        .table-card { background: #1e293b; border-radius: 0.5rem; border: 1px solid #334155; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 1rem; border-bottom: 1px solid #334155; }
        th { background: #0f172a; font-weight: 600; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 style="margin: 0; font-size: 1.75rem;">Commerce & POS Management</h1>
                <p style="margin: 0.5rem 0 0; color: #94a3b8;">Enterprise E-Commerce, POS Checkout & Inventory Ledger Sync</p>
            </div>
            <span class="badge">NetFusion HMVC</span>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div>Total Orders</div>
                <div class="stat-val"><?= htmlspecialchars((string)($ordersCount ?? 0)) ?></div>
            </div>
            <div class="stat-card">
                <div>Registered Customers</div>
                <div class="stat-val"><?= htmlspecialchars((string)($customersCount ?? 0)) ?></div>
            </div>
            <div class="stat-card">
                <div>Total Revenue (Paid)</div>
                <div class="stat-val">৳ <?= number_format((float)($totalSales ?? 0), 2) ?></div>
            </div>
        </div>

        <div class="table-card">
            <div style="padding: 1rem 1.5rem; font-weight: 600; border-bottom: 1px solid #334155;">Recent Orders</div>
            <table>
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Type</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentOrders)): ?>
                        <tr><td colspan="6" style="text-align: center; color: #94a3b8;">No orders recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['order_number']) ?></td>
                                <td><?= strtoupper(htmlspecialchars($order['type'])) ?></td>
                                <td>৳ <?= number_format((float)$order['total_amount'], 2) ?></td>
                                <td>৳ <?= number_format((float)$order['paid_amount'], 2) ?></td>
                                <td><span style="color: <?= $order['payment_status'] === 'paid' ? '#34d399' : '#fbbf24' ?>;"><?= ucfirst(htmlspecialchars($order['payment_status'])) ?></span></td>
                                <td><?= htmlspecialchars($order['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
