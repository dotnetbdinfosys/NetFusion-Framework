<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetFusion Inventory & Supply Chain</title>
    <style>
        :root {
            --bg: #0f172a;
            --surface: #1e293b;
            --border: #334155;
            --primary: #38bdf8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text: #f8fafc;
            --muted: #94a3b8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 2rem; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 2rem; }
        .header h1 { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; }
        .card h3 { font-size: 0.875rem; text-transform: uppercase; color: var(--muted); margin-bottom: 0.5rem; }
        .card .value { font-size: 1.75rem; font-weight: 700; color: var(--text); }
        .table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; margin-bottom: 2rem; }
        .table-title { padding: 1rem 1.25rem; font-weight: 600; border-bottom: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem; }
        th, td { padding: 0.75rem 1.25rem; border-bottom: 1px solid var(--border); }
        th { background: rgba(0,0,0,0.2); color: var(--muted); font-weight: 600; }
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .badge-success { background: rgba(16,185,129,0.2); color: var(--success); }
        .badge-danger { background: rgba(239,68,68,0.2); color: var(--danger); }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>NetFusion Inventory & Warehouse Dashboard</h1>
            <p style="color: var(--muted); font-size: 0.875rem;">Multi-Warehouse Stock Control & Audit Movements</p>
        </div>
        <div>
            <span class="badge badge-success">System Active</span>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h3>Total Inventory Valuation</h3>
            <div class="value" style="color: var(--primary);">$<?= number_format((float) ($valuation ?? 0), 2) ?></div>
        </div>
        <div class="card">
            <h3>Active Warehouses</h3>
            <div class="value"><?= count($warehouses ?? []) ?></div>
        </div>
        <div class="card">
            <h3>Total SKUs Registered</h3>
            <div class="value"><?= count($products ?? []) ?></div>
        </div>
        <div class="card">
            <h3>Low Stock Alerts</h3>
            <div class="value" style="color: <?= count($lowStock ?? []) > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                <?= count($lowStock ?? []) ?>
            </div>
        </div>
    </div>

    <div class="table-wrap">
        <div class="table-title">Product Stock Overview</div>
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th>Unit</th>
                    <th style="text-align: right;">Cost Price</th>
                    <th style="text-align: right;">Selling Price</th>
                    <th style="text-align: right;">Current Stock</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($products ?? []) as $prod): ?>
                <tr>
                    <td><code><?= htmlspecialchars($prod->sku ?? '') ?></code></td>
                    <td><?= htmlspecialchars($prod->name ?? '') ?></td>
                    <td><?= htmlspecialchars($prod->unit ?? '') ?></td>
                    <td style="text-align: right;">$<?= number_format((float) ($prod->cost_price ?? 0), 2) ?></td>
                    <td style="text-align: right;">$<?= number_format((float) ($prod->selling_price ?? 0), 2) ?></td>
                    <td style="text-align: right;"><?= number_format((float) $prod->totalQuantity(), 2) ?></td>
                    <td>
                        <?php if ($prod->isLowStock()): ?>
                            <span class="badge badge-danger">Low Stock</span>
                        <?php else: ?>
                            <span class="badge badge-success">In Stock</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
