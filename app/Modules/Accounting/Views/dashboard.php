<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetFusion Accounting & General Ledger</title>
    <style>
        :root {
            --bg: #0f172a;
            --surface: #1e293b;
            --border: #334155;
            --primary: #38bdf8;
            --success: #10b981;
            --warning: #f59e0b;
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
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>NetFusion Accounting & Ledger Dashboard</h1>
            <p style="color: var(--muted); font-size: 0.875rem;">Double-Entry General Ledger & Real-Time Financial Statements</p>
        </div>
        <div>
            <span class="badge badge-success">Ledger Balanced: <?= !empty($trialBalance['is_balanced']) ? 'YES' : 'NO' ?></span>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h3>Total Assets</h3>
            <div class="value" style="color: var(--primary);">$<?= number_format((float) ($balanceSheet['assets']['total'] ?? 0), 2) ?></div>
        </div>
        <div class="card">
            <h3>Total Revenue</h3>
            <div class="value" style="color: var(--success);">$<?= number_format((float) ($incomeStatement['revenue']['total'] ?? 0), 2) ?></div>
        </div>
        <div class="card">
            <h3>Total Expenses</h3>
            <div class="value" style="color: var(--warning);">$<?= number_format((float) ($incomeStatement['expenses']['total'] ?? 0), 2) ?></div>
        </div>
        <div class="card">
            <h3>Net Income</h3>
            <div class="value">$<?= number_format((float) ($incomeStatement['net_income'] ?? 0), 2) ?></div>
        </div>
    </div>

    <div class="table-wrap">
        <div class="table-title">Trial Balance</div>
        <table>
            <thead>
                <tr>
                    <th>Account Code</th>
                    <th>Account Name</th>
                    <th>Type</th>
                    <th style="text-align: right;">Debit ($)</th>
                    <th style="text-align: right;">Credit ($)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($trialBalance['accounts'] ?? []) as $acc): ?>
                <tr>
                    <td><code><?= htmlspecialchars($acc['code']) ?></code></td>
                    <td><?= htmlspecialchars($acc['name']) ?></td>
                    <td><?= htmlspecialchars($acc['type']) ?></td>
                    <td style="text-align: right;"><?= number_format((float) $acc['debit'], 2) ?></td>
                    <td style="text-align: right;"><?= number_format((float) $acc['credit'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight: 700; background: rgba(0,0,0,0.3);">
                    <td colspan="3">Total</td>
                    <td style="text-align: right;">$<?= number_format((float) ($trialBalance['total_debit'] ?? 0), 2) ?></td>
                    <td style="text-align: right;">$<?= number_format((float) ($trialBalance['total_credit'] ?? 0), 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
