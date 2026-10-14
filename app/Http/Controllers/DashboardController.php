<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Http/Controllers/DashboardController.php
| Module    : Dashboard
| Namespace : App\Http\Controllers
| Purpose   : Unified Master Executive Dashboard aggregating cross-module enterprise KPIs.
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

namespace NetFusion\App\Http\Controllers;

use NetFusion\Core\Audit\Models\AuditLog;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\Security\SecurityAuditor;
use Throwable;

class DashboardController
{
    /**
     * Display the Unified Master Executive Dashboard.
     */
    public function index(Request $request): Response
    {
        $metrics = $this->collectEnterpriseMetrics();
        return Response::html($this->renderDashboardHtml($metrics));
    }

    /**
     * API endpoint returning real-time metrics for client polling.
     */
    public function metrics(Request $request): Response
    {
        return Response::json($this->collectEnterpriseMetrics());
    }

    /**
     * Gather live cross-module metrics and health data.
     *
     * @return array<string, mixed>
     */
    public function collectEnterpriseMetrics(): array
    {
        $metrics = [
            'overview' => [
                'app_name'     => config('app.name', 'NetFusion Enterprise'),
                'environment'  => app()->environment(),
                'version'      => app()->version(),
                'php_version'  => PHP_VERSION,
                'uptime_ms'    => app()->elapsedTime(),
                'memory_mb'    => app()->peakMemory(),
            ],
            'commerce'   => ['orders' => 0, 'sales_volume' => 0.0, 'customers' => 0],
            'accounting' => ['total_assets' => 0.0, 'net_profit' => 0.0],
            'inventory'  => ['products' => 0, 'low_stock' => 0, 'warehouses' => 0],
            'crm'        => ['leads' => 0, 'pipeline_value' => 0.0, 'open_tickets' => 0],
            'security'   => ['score' => 88, 'rating' => 'B (Good)'],
            'recent_audits' => [],
        ];

        // 1. Live Database Queries (safe fail if tables not yet migrated)
        try {
            /** @var DatabaseManager $dbManager */
            $dbManager = app(DatabaseManager::class);

            // Commerce metrics
            if ($this->tableExists('pos_orders')) {
                $ordersCount = $dbManager->table('pos_orders')->count();
                $totalSales  = $dbManager->table('pos_orders')->where('payment_status', 'paid')->sum('total_amount');
                $custCount   = $this->tableExists('pos_customers') ? $dbManager->table('pos_customers')->count() : 0;

                $metrics['commerce'] = [
                    'orders'       => (int) $ordersCount,
                    'sales_volume' => (float) $totalSales,
                    'customers'    => (int) $custCount,
                ];
            }

            // Inventory metrics
            if ($this->tableExists('inv_products')) {
                $prodCount = $dbManager->table('inv_products')->count();
                $whCount   = $this->tableExists('inv_warehouses') ? $dbManager->table('inv_warehouses')->count() : 0;
                $lowStock  = $this->tableExists('inv_warehouse_stocks')
                    ? $dbManager->table('inv_warehouse_stocks')->where('quantity', '<', 5)->count()
                    : 0;

                $metrics['inventory'] = [
                    'products'   => (int) $prodCount,
                    'low_stock'  => (int) $lowStock,
                    'warehouses' => (int) $whCount,
                ];
            }

            // CRM metrics
            if ($this->tableExists('crm_leads')) {
                $leadsCount = $dbManager->table('crm_leads')->count();
                $dealsValue = $this->tableExists('crm_deals')
                    ? $dbManager->table('crm_deals')->where('stage', '!=', 'lost')->sum('value')
                    : 0.0;
                $openTickets = $this->tableExists('crm_tickets')
                    ? $dbManager->table('crm_tickets')->where('status', '!=', 'resolved')->count()
                    : 0;

                $metrics['crm'] = [
                    'leads'          => (int) $leadsCount,
                    'pipeline_value' => (float) $dealsValue,
                    'open_tickets'   => (int) $openTickets,
                ];
            }

            // Recent Audits
            if ($this->tableExists('audit_logs')) {
                $recent = $dbManager->table('audit_logs')
                    ->orderBy('created_at', 'DESC')
                    ->limit(5)
                    ->get();
                $metrics['recent_audits'] = $recent;
            }
        } catch (Throwable) {
            // Gracefully continue with fallback values if database offline
        }

        // 2. Security Auditor Score
        try {
            $auditor = new SecurityAuditor();
            $sec = $auditor->audit();
            $metrics['security'] = [
                'score'  => $sec['score'],
                'rating' => $sec['rating'],
            ];
        } catch (Throwable) {
        }

        return $metrics;
    }

    protected function tableExists(string $table): bool
    {
        try {
            /** @var DatabaseManager $dbManager */
            $dbManager = app(DatabaseManager::class);
            $conn = $dbManager->connection();
            $driver = $conn->getDriverName();

            if ($driver === 'sqlite') {
                $res = $conn->select("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$table]);
                return !empty($res);
            }

            $res = $conn->select("SHOW TABLES LIKE ?", [$table]);
            return !empty($res);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Render modern, responsive HTML for the Executive Dashboard.
     */
    protected function renderDashboardHtml(array $metrics): string
    {
        $salesFormatted = number_format((float) $metrics['commerce']['sales_volume'], 2);
        $dealsFormatted = number_format((float) $metrics['crm']['pipeline_value'], 2);

        $auditRows = '';
        if (!empty($metrics['recent_audits'])) {
            foreach ($metrics['recent_audits'] as $log) {
                $auditable = class_basename((string) ($log['auditable_type'] ?? ''));
                $event     = strtoupper((string) ($log['event'] ?? 'INFO'));
                $ip        = htmlspecialchars((string) ($log['ip_address'] ?? '127.0.0.1'));
                $time      = htmlspecialchars((string) ($log['created_at'] ?? ''));

                $auditRows .= "<tr>
                    <td><span class='badge'>{$event}</span></td>
                    <td><strong>{$auditable}</strong> #{$log['auditable_id']}</td>
                    <td>{$ip}</td>
                    <td style='color:#94a3b8'>{$time}</td>
                </tr>";
            }
        } else {
            $auditRows = "<tr><td colspan='4' style='text-align:center; color:#64748b; padding:20px;'>No audit activity recorded yet.</td></tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetFusion Framework — Executive Dashboard</title>
    <style>
        :root { --primary: #3b82f6; --bg: #0b0f19; --card: #151d30; --text: #f8fafc; --text-muted: #94a3b8; --border: #1e293b; --success: #22c55e; --warning: #f59e0b; --danger: #ef4444; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body { background-color: var(--bg); color: var(--text); padding: 24px; min-height: 100vh; }
        .container { max-width: 1200px; margin: 0 auto; }
        .top-nav { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 16px; margin-bottom: 24px; }
        .logo { font-size: 20px; font-weight: bold; color: #fff; display: flex; align-items: center; gap: 8px; }
        .logo span { color: var(--primary); }
        .badge-env { background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.4); color: #93c5fd; padding: 4px 10px; border-radius: 12px; font-size: 12px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 20px; transition: 0.2s; }
        .kpi-card:hover { border-color: #3b82f6; }
        .kpi-title { font-size: 13px; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-val { font-size: 28px; font-weight: 700; color: #fff; margin: 10px 0 4px 0; }
        .kpi-sub { font-size: 13px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }
        .kpi-sub.green { color: var(--success); }
        .kpi-sub.amber { color: var(--warning); }
        .card-panel { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 24px; margin-bottom: 24px; }
        .panel-header { font-size: 16px; font-weight: 600; margin-bottom: 16px; color: #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 12px; border-bottom: 1px solid var(--border); text-align: left; }
        th { color: var(--text-muted); font-size: 12px; text-transform: uppercase; }
        .badge { background: #1e293b; color: #93c5fd; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .footer { text-align: center; color: #475569; font-size: 13px; margin-top: 40px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="top-nav">
            <div class="logo">Net<span>Fusion</span> Executive Dashboard</div>
            <div style="display:flex; align-items:center; gap:12px;">
                <span class="badge-env">{$metrics['overview']['environment']} &bull; PHP {$metrics['overview']['php_version']}</span>
                <span style="font-size:13px; color:var(--text-muted)">Author: Md. Atiquzzaman Ashish (DotNetBD)</span>
            </div>
        </div>

        <div class="grid">
            <div class="kpi-card">
                <div class="kpi-title">Commerce & POS Sales</div>
                <div class="kpi-val">\${$salesFormatted}</div>
                <div class="kpi-sub green">✔ {$metrics['commerce']['orders']} Orders Processed</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-title">CRM Pipeline Value</div>
                <div class="kpi-val">\${$dealsFormatted}</div>
                <div class="kpi-sub">{$metrics['crm']['leads']} Active Leads &bull; {$metrics['crm']['open_tickets']} Open Tickets</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-title">Inventory Valuation</div>
                <div class="kpi-val">{$metrics['inventory']['products']} Products</div>
                <div class="kpi-sub amber">⚠ {$metrics['inventory']['low_stock']} Low Stock Alerts</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-title">Security & Health</div>
                <div class="kpi-val">{$metrics['security']['score']}%</div>
                <div class="kpi-sub green">Rating: {$metrics['security']['rating']}</div>
            </div>
        </div>

        <div class="card-panel">
            <div class="panel-header">
                <span>Recent Audit Trail & Model Revision History</span>
                <span class="badge">Live Audit Engine</span>
            </div>
            <table>
                <thead>
                    <tr><th>Action</th><th>Target Entity</th><th>IP Address</th><th>Timestamp</th></tr>
                </thead>
                <tbody>
                    {$auditRows}
                </tbody>
            </table>
        </div>

        <div class="footer">
            NetFusion Framework v{$metrics['overview']['version']} &bull; © DotNetBD. All rights reserved. &bull; www.dotnetbd.com
        </div>
    </div>
</body>
</html>
HTML;
    }
}
