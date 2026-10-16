<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Http/Helpers/ModuleLayoutHelper.php
| Purpose   : Shared sidebar + layout renderer for all HMVC module dashboards.
|             Provides a consistent NetFusion light-theme shell so every module
|             dashboard uses the same header, sidebar, and footer structure.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.3.5
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\App\Http\Helpers;

/**
 * Shared layout helper for all HMVC Module Dashboards.
 *
 * Usage:
 *   $html = ModuleLayoutHelper::wrap('accounting', 'Dashboard', $bodyHtml, $extraCss, $extraJs);
 */
class ModuleLayoutHelper
{
    /** @var array<string, array<string, mixed>> Registry of all known modules */
    private static array $moduleRegistry = [
        'accounting' => [
            'name'  => 'Accounting',
            'bn'    => 'অ্যাকাউন্টিং',
            'icon'  => '📊',
            'color' => '#2563eb',
            'url'   => '/accounting',
            'menu'  => [
                ['icon' => '📊', 'label' => 'ড্যাশবোর্ড', 'url' => '/accounting'],
                ['icon' => '📋', 'label' => 'চার্ট অব অ্যাকাউন্টস', 'url' => '/accounting/chart-of-accounts'],
                ['icon' => '📝', 'label' => 'জার্নাল এন্ট্রি', 'url' => '/accounting/journal'],
                ['icon' => '📈', 'label' => 'লেজার বুক', 'url' => '/accounting/ledger'],
                ['icon' => '⚖️', 'label' => 'ট্রায়াল ব্যালেন্স', 'url' => '/accounting/trial-balance'],
                ['icon' => '💰', 'label' => 'আয় বিবরণী', 'url' => '/accounting/income-statement'],
                ['icon' => '🏦', 'label' => 'ব্যালেন্স শিট', 'url' => '/accounting/balance-sheet'],
                ['icon' => '📦', 'label' => 'ভ্যাট / ট্যাক্স রিপোর্ট', 'url' => '/accounting/tax-report'],
            ],
        ],
        'billing' => [
            'name'  => 'Billing',
            'bn'    => 'বিলিং',
            'icon'  => '💳',
            'color' => '#7c3aed',
            'url'   => '/billing',
            'menu'  => [
                ['icon' => '💳', 'label' => 'ড্যাশবোর্ড', 'url' => '/billing'],
                ['icon' => '📄', 'label' => 'ইনভয়েস তালিকা', 'url' => '/billing/invoices'],
                ['icon' => '🔁', 'label' => 'সাবস্ক্রিপশন', 'url' => '/billing/subscriptions'],
                ['icon' => '📦', 'label' => 'প্ল্যান সমূহ', 'url' => '/billing/plans'],
                ['icon' => '💵', 'label' => 'পেমেন্ট হিস্টোরি', 'url' => '/billing/payments'],
                ['icon' => '⚠️', 'label' => 'বকেয়া ও ড্যু', 'url' => '/billing/overdue'],
            ],
        ],
        'crm' => [
            'name'  => 'CRM',
            'bn'    => 'সিআরএম',
            'icon'  => '👥',
            'color' => '#059669',
            'url'   => '/crm',
            'menu'  => [
                ['icon' => '👥', 'label' => 'ড্যাশবোর্ড', 'url' => '/crm'],
                ['icon' => '🎯', 'label' => 'লিড ম্যানেজমেন্ট', 'url' => '/crm/leads'],
                ['icon' => '💼', 'label' => 'ডিল পাইপলাইন', 'url' => '/crm/deals'],
                ['icon' => '🏢', 'label' => 'কোম্পানি ডিরেক্টরি', 'url' => '/crm/companies'],
                ['icon' => '📞', 'label' => 'কনটাক্ট ম্যানেজার', 'url' => '/crm/contacts'],
                ['icon' => '🎫', 'label' => 'সাপোর্ট টিকিট', 'url' => '/crm/tickets'],
                ['icon' => '📊', 'label' => 'সেলস অ্যানালিটিকস', 'url' => '/crm/analytics'],
            ],
        ],
        'commerce' => [
            'name'  => 'Commerce',
            'bn'    => 'কমার্স',
            'icon'  => '🛍️',
            'color' => '#d97706',
            'url'   => '/commerce',
            'menu'  => [
                ['icon' => '🛍️', 'label' => 'ড্যাশবোর্ড', 'url' => '/commerce'],
                ['icon' => '📋', 'label' => 'অর্ডার ম্যানেজমেন্ট', 'url' => '/commerce/orders'],
                ['icon' => '📦', 'label' => 'প্রোডাক্ট ক্যাটালগ', 'url' => '/commerce/products'],
                ['icon' => '👤', 'label' => 'গ্রাহক তালিকা', 'url' => '/commerce/customers'],
                ['icon' => '💳', 'label' => 'পেমেন্ট গেটওয়ে', 'url' => '/commerce/payments'],
                ['icon' => '🏷️', 'label' => 'কুপন ও ছাড়', 'url' => '/commerce/coupons'],
                ['icon' => '📊', 'label' => 'সেলস রিপোর্ট', 'url' => '/commerce/reports'],
            ],
        ],
        'hrm' => [
            'name'  => 'HRM',
            'bn'    => 'এইচআরএম',
            'icon'  => '👔',
            'color' => '#dc2626',
            'url'   => '/hrm',
            'menu'  => [
                ['icon' => '👔', 'label' => 'ড্যাশবোর্ড', 'url' => '/hrm'],
                ['icon' => '👤', 'label' => 'কর্মচারী তালিকা', 'url' => '/hrm/employees'],
                ['icon' => '🏢', 'label' => 'বিভাগ ব্যবস্থাপনা', 'url' => '/hrm/departments'],
                ['icon' => '⏰', 'label' => 'উপস্থিতি ট্র্যাকিং', 'url' => '/hrm/attendance'],
                ['icon' => '📅', 'label' => 'ছুটির ব্যবস্থাপনা', 'url' => '/hrm/leave'],
                ['icon' => '💰', 'label' => 'বেতন ব্যবস্থাপনা', 'url' => '/hrm/payroll'],
                ['icon' => '📊', 'label' => 'পারফরম্যান্স রিভিউ', 'url' => '/hrm/performance'],
            ],
        ],
        'inventory' => [
            'name'  => 'Inventory',
            'bn'    => 'ইনভেন্টরি',
            'icon'  => '📦',
            'color' => '#0891b2',
            'url'   => '/inventory',
            'menu'  => [
                ['icon' => '📦', 'label' => 'ড্যাশবোর্ড', 'url' => '/inventory'],
                ['icon' => '📋', 'label' => 'পণ্য তালিকা', 'url' => '/inventory/products'],
                ['icon' => '🏭', 'label' => 'গুদামঘর', 'url' => '/inventory/warehouses'],
                ['icon' => '📥', 'label' => 'স্টক ইন (আগমন)', 'url' => '/inventory/stock-in'],
                ['icon' => '📤', 'label' => 'স্টক আউট (নির্গমন)', 'url' => '/inventory/stock-out'],
                ['icon' => '⚠️', 'label' => 'লো-স্টক অ্যালার্ট', 'url' => '/inventory/low-stock'],
                ['icon' => '📊', 'label' => 'স্টক ভ্যালুয়েশন', 'url' => '/inventory/valuation'],
            ],
        ],
        'system' => [
            'name'  => 'System',
            'bn'    => 'সিস্টেম',
            'icon'  => '⚙️',
            'color' => '#64748b',
            'url'   => '/system/status',
            'menu'  => [
                ['icon' => '⚙️', 'label' => 'ড্যাশবোর্ড', 'url' => '/system/status'],
                ['icon' => '📦', 'label' => 'মডিউল ইনস্পেক্টর', 'url' => '/system/modules'],
                ['icon' => '🩺', 'label' => 'সিস্টেম হেলথ', 'url' => '/health'],
                ['icon' => '📄', 'label' => 'কনফিগারেশন', 'url' => '/system/status'],
                ['icon' => '🔒', 'label' => 'নিরাপত্তা অডিট', 'url' => '/system/status'],
            ],
        ],
    ];

    /**
     * Wrap module body content in the shared NetFusion layout.
     *
     * @param string $moduleKey    e.g. 'accounting', 'crm', 'hrm'
     * @param string $pageTitle    Page-specific title
     * @param string $bodyHtml     The main content HTML
     * @param string $extraCss     Optional additional CSS rules
     * @param string $extraJs      Optional additional JS code
     * @param string $currentUrl   Active page URL for menu highlighting (default: module home)
     */
    public static function wrap(
        string $moduleKey,
        string $pageTitle,
        string $bodyHtml,
        string $extraCss = '',
        string $extraJs = '',
        string $currentUrl = '',
    ): string {
        $module  = self::$moduleRegistry[$moduleKey] ?? self::$moduleRegistry['system'];
        $version = app()->version();
        $color   = $module['color'];
        $iconStr = $module['icon'];
        $modName = htmlspecialchars($module['name']);
        $modBn   = htmlspecialchars($module['bn']);
        $current = $currentUrl ?: $module['url'];

        // Build sidebar nav items
        $navHtml = '';
        foreach ($module['menu'] as $item) {
            $isActive = ($item['url'] === $current) ? 'active' : '';
            $navHtml .= sprintf(
                '<a href="%s" class="nav-item %s"><span class="nav-icon">%s</span> %s</a>',
                htmlspecialchars($item['url']),
                $isActive,
                $item['icon'],
                htmlspecialchars($item['label'])
            );
        }

        // Build cross-module quick links
        $crossModuleHtml = '';
        $crossLinks = [
            ['icon' => '📊', 'label' => 'Accounting', 'url' => '/accounting'],
            ['icon' => '💳', 'label' => 'Billing', 'url' => '/billing'],
            ['icon' => '👥', 'label' => 'CRM', 'url' => '/crm'],
            ['icon' => '🛍️', 'label' => 'Commerce', 'url' => '/commerce'],
            ['icon' => '👔', 'label' => 'HRM', 'url' => '/hrm'],
            ['icon' => '📦', 'label' => 'Inventory', 'url' => '/inventory'],
            ['icon' => '⚙️', 'label' => 'System', 'url' => '/system/status'],
        ];
        foreach ($crossLinks as $cl) {
            if ($cl['label'] === $module['name']) {
                continue; // Skip current module
            }
            $crossModuleHtml .= sprintf(
                '<a href="%s" class="nav-item" style="font-size:12px; opacity:0.8;"><span class="nav-icon">%s</span> %s</a>',
                $cl['url'],
                $cl['icon'],
                $cl['label']
            );
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$modName} — {$pageTitle} | NetFusion v{$version}</title>
    <style>
        :root {
            --primary: {$color};
            --primary-light: color-mix(in srgb, {$color} 10%, white);
            --primary-hover: color-mix(in srgb, {$color} 80%, black);
            --bg-body: #f8fafc;
            --bg-surface: #ffffff;
            --border-color: #e2e8f0;
            --text-heading: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
            --success: #10b981;
            --success-light: #ecfdf5;
            --warning: #f59e0b;
            --warning-light: #fffbeb;
            --danger: #ef4444;
            --danger-light: #fef2f2;
            --info-light: #eff6ff;
            --sidebar-width: 264px;
            --header-height: 64px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.07);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; }
        body { background: var(--bg-body); color: var(--text-body); min-height: 100vh; }
        .app-shell { display: flex; min-height: 100vh; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-surface);
            border-right: 1px solid var(--border-color);
            display: flex; flex-direction: column;
            position: fixed; top: 0; bottom: 0; left: 0; z-index: 200;
            overflow-y: auto;
        }
        .sidebar-brand {
            height: var(--header-height);
            display: flex; align-items: center; padding: 0 20px; gap: 12px;
            border-bottom: 1px solid var(--border-color);
            position: sticky; top: 0; background: var(--bg-surface); z-index: 10;
        }
        .brand-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 70%, white));
            color: #fff; border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 17px; font-weight: 800;
            box-shadow: 0 2px 6px color-mix(in srgb, var(--primary) 35%, transparent);
        }
        .brand-text h2 { font-size: 15px; font-weight: 700; color: var(--text-heading); line-height: 1.2; }
        .brand-text span { font-size: 11px; font-weight: 600; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; }

        .sidebar-section { padding: 16px 12px 8px; }
        .menu-label { font-size: 10px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; padding: 10px 10px 4px; }
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 12px; border-radius: 8px;
            color: var(--text-body); text-decoration: none;
            font-size: 13.5px; font-weight: 500; margin-bottom: 2px;
            transition: all 0.15s ease;
        }
        .nav-item:hover { background: var(--bg-body); color: var(--text-heading); }
        .nav-item.active { background: var(--primary-light); color: var(--primary); font-weight: 700; }
        .nav-item .nav-icon { font-size: 16px; width: 20px; display: inline-flex; justify-content: center; }

        .sidebar-divider { height: 1px; background: var(--border-color); margin: 10px 16px; }
        .sidebar-bottom { margin-top: auto; padding: 14px 16px; border-top: 1px solid var(--border-color); font-size: 11px; color: var(--text-muted); }
        .sidebar-bottom a { color: var(--primary); text-decoration: none; font-weight: 600; }

        /* Module Badge in Sidebar */
        .module-badge-bar {
            margin: 12px 12px 4px;
            padding: 10px 14px;
            background: var(--primary-light);
            border: 1px solid color-mix(in srgb, var(--primary) 25%, transparent);
            border-radius: 10px;
            display: flex; align-items: center; gap: 10px;
        }
        .module-badge-icon { font-size: 22px; }
        .module-badge-text { flex: 1; }
        .module-badge-text strong { font-size: 13px; color: var(--primary); display: block; font-weight: 700; }
        .module-badge-text span { font-size: 11px; color: var(--text-muted); }

        /* ── WORKSPACE ── */
        .workspace { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; min-width: 0; }

        /* ── TOP HEADER ── */
        .top-header {
            height: var(--header-height);
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border-color);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 28px;
            position: sticky; top: 0; z-index: 100;
            box-shadow: var(--shadow-sm);
        }
        .header-breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .breadcrumb-sep { color: var(--text-muted); }
        .breadcrumb-current { font-weight: 700; color: var(--text-heading); }
        .header-right { display: flex; align-items: center; gap: 12px; }
        .header-badge {
            background: var(--primary-light);
            color: var(--primary);
            padding: 5px 12px; border-radius: 20px;
            font-size: 12px; font-weight: 700;
            border: 1px solid color-mix(in srgb, var(--primary) 25%, transparent);
        }
        .header-back-btn {
            display: flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 8px;
            background: var(--bg-body); border: 1px solid var(--border-color);
            font-size: 12px; font-weight: 600; color: var(--text-body);
            text-decoration: none; transition: all 0.15s;
        }
        .header-back-btn:hover { background: var(--border-color); color: var(--text-heading); }

        /* ── CONTENT BODY ── */
        .content-body { flex: 1; padding: 28px 32px; max-width: 1440px; width: 100%; margin: 0 auto; }

        /* ── KPI GRID ── */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 28px; }
        .kpi-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 20px 22px;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s;
        }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .kpi-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .kpi-label { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-icon { width: 38px; height: 38px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .kpi-val { font-size: 28px; font-weight: 800; color: var(--text-heading); margin-bottom: 4px; letter-spacing: -0.5px; }
        .kpi-meta { font-size: 12px; color: var(--text-muted); }

        /* ── SECTION HEADER ── */
        .section-header { display: flex; justify-content: space-between; align-items: center; margin: 28px 0 14px; flex-wrap: wrap; gap: 10px; }
        .section-title { font-size: 16px; font-weight: 700; color: var(--text-heading); display: flex; align-items: center; gap: 8px; }

        /* ── TABLE CARD ── */
        .table-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            overflow: hidden; margin-bottom: 24px;
        }
        .table-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex; justify-content: space-between; align-items: center;
        }
        .table-card-title { font-size: 14px; font-weight: 700; color: var(--text-heading); display: flex; align-items: center; gap: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { padding: 11px 16px; font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; background: #f8fafc; text-align: left; border-bottom: 1px solid var(--border-color); }
        td { padding: 12px 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafafa; }

        /* ── BADGES ── */
        .badge { display: inline-block; padding: 3px 9px; border-radius: 12px; font-size: 11px; font-weight: 700; }
        .badge-success { background: var(--success-light); color: #065f46; }
        .badge-warning { background: var(--warning-light); color: #92400e; }
        .badge-danger  { background: var(--danger-light);  color: #991b1b; }
        .badge-info    { background: var(--info-light);    color: #1d4ed8; }
        .badge-muted   { background: #f1f5f9; color: #475569; }

        /* ── PROGRESS BAR ── */
        .progress-wrap { background: #e2e8f0; border-radius: 10px; height: 7px; overflow: hidden; }
        .progress-bar  { height: 100%; border-radius: 10px; background: var(--primary); transition: width 0.4s ease; }

        /* ── BUTTONS ── */
        .btn { display: inline-flex; align-items: center; gap: 7px; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; transition: all 0.2s; border: 1px solid transparent; }
        .btn-sm { padding: 5px 11px; font-size: 12px; border-radius: 6px; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); box-shadow: 0 4px 8px color-mix(in srgb, var(--primary) 25%, transparent); }
        .btn-outline { background: #fff; color: var(--text-body); border-color: var(--border-color); }
        .btn-outline:hover { background: var(--bg-body); }

        /* ── ALERT CARDS ── */
        .alert-card { border-radius: var(--radius-md); padding: 14px 18px; display: flex; align-items: flex-start; gap: 12px; margin-bottom: 14px; }
        .alert-warning { background: var(--warning-light); border: 1px solid #fde68a; }
        .alert-danger  { background: var(--danger-light);  border: 1px solid #fecaca; }
        .alert-success { background: var(--success-light); border: 1px solid #a7f3d0; }
        .alert-info    { background: var(--info-light);    border: 1px solid #bfdbfe; }

        /* ── FOOTER ── */
        .app-footer {
            background: var(--bg-surface); border-top: 1px solid var(--border-color);
            padding: 16px 28px; font-size: 12px; color: var(--text-muted);
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;
        }
        .footer-links { display: flex; gap: 16px; }
        .footer-links a { color: var(--text-muted); text-decoration: none; font-weight: 500; }
        .footer-links a:hover { color: var(--primary); }

        /* ── GRID HELPERS ── */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 18px; margin-bottom: 24px; }
        .card { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 20px; box-shadow: var(--shadow-sm); }
        .card-title { font-size: 14px; font-weight: 700; color: var(--text-heading); margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .workspace { margin-left: 0; }
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
        }
        {$extraCss}
    </style>
</head>
<body>
<div class="app-shell">
    <!-- ══ SIDEBAR ══ -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">{$iconStr}</div>
            <div class="brand-text">
                <h2>NetFusion</h2>
                <span>{$modBn} মডিউল</span>
            </div>
        </div>

        <div class="module-badge-bar">
            <div class="module-badge-icon">{$iconStr}</div>
            <div class="module-badge-text">
                <strong>{$modName} Module</strong>
                <span>NetFusion v{$version}</span>
            </div>
        </div>

        <div class="sidebar-section">
            <div class="menu-label">মডিউল মেন্যু ({$modName})</div>
            {$navHtml}
        </div>

        <div class="sidebar-divider"></div>

        <div class="sidebar-section">
            <div class="menu-label">অন্য মডিউলসমূহ</div>
            {$crossModuleHtml}
            <a href="/admin/dashboard" class="nav-item" style="font-size:12px; margin-top:6px; border-top: 1px solid var(--border-color); padding-top: 10px;">
                <span class="nav-icon">🏠</span> মূল ড্যাশবোর্ড
            </a>
        </div>

        <div class="sidebar-bottom">
            <div>NetFusion Framework <a href="/admin/dashboard">v{$version}</a></div>
            <div style="margin-top:2px; font-size:10px;">© DotNetBD — MIT License</div>
        </div>
    </aside>

    <!-- ══ WORKSPACE ══ -->
    <div class="workspace">
        <!-- TOP HEADER -->
        <header class="top-header">
            <div class="header-breadcrumb">
                <a href="/admin/dashboard" style="color:var(--text-muted); text-decoration:none;">🏠 Dashboard</a>
                <span class="breadcrumb-sep">›</span>
                <a href="{$module['url']}" style="color:var(--text-muted); text-decoration:none;">{$modName}</a>
                <span class="breadcrumb-sep">›</span>
                <span class="breadcrumb-current">{$pageTitle}</span>
            </div>
            <div class="header-right">
                <span class="header-badge">{$iconStr} {$modName} Module</span>
                <a href="/admin/dashboard" class="header-back-btn">← মূল ড্যাশবোর্ড</a>
            </div>
        </header>

        <!-- BODY -->
        <main class="content-body">
            {$bodyHtml}
        </main>

        <!-- FOOTER -->
        <footer class="app-footer">
            <div><strong>NetFusion {$modName} Module</strong> &bull; Version {$version} (Fixed) &bull; &copy; 2026 <strong>DotNetBD</strong></div>
            <div class="footer-links">
                <a href="/admin/dashboard">ড্যাশবোর্ড</a>
                <a href="/license">লাইসেন্স</a>
                <a href="https://www.dotnetbd.com" target="_blank">DotNetBD</a>
            </div>
        </footer>
    </div>
</div>
{$extraJs}
</body>
</html>
HTML;
    }

    /**
     * Render a single KPI card HTML.
     */
    public static function kpiCard(
        string $label,
        string $value,
        string $icon,
        string $iconClass = '',
        string $meta = '',
        string $iconBg = '#eff6ff',
        string $iconColor = '#2563eb'
    ): string {
        return <<<HTML
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-label">{$label}</span>
                <span class="kpi-icon" style="background:{$iconBg}; color:{$iconColor};">{$icon}</span>
            </div>
            <div class="kpi-val">{$value}</div>
            <div class="kpi-meta">{$meta}</div>
        </div>
HTML;
    }

    /**
     * Format number as currency with BDT symbol.
     */
    public static function currency(float $amount, string $symbol = '৳'): string
    {
        return $symbol . ' ' . number_format($amount, 2);
    }

    /**
     * Get a status badge HTML.
     */
    public static function badge(string $text, string $type = 'info'): string
    {
        return "<span class=\"badge badge-{$type}\">" . htmlspecialchars($text) . "</span>";
    }
}
