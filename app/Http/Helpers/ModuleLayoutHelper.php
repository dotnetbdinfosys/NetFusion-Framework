<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Http/Helpers/ModuleLayoutHelper.php
| Purpose   : Shared layout renderer for all HMVC module dashboards.
|             Supports English (Default) & Bengali (Secondary), dynamic
|             header/footer/logo/favicon customization, and clean versioning.
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
 */
class ModuleLayoutHelper
{
    /** @var array<string, array<string, mixed>> Registry of all known modules with English & Bengali labels */
    private static array $moduleRegistry = [
        'accounting' => [
            'name'     => 'Accounting',
            'name_bn'  => 'অ্যাকাউন্টিং',
            'icon'     => '📊',
            'color'    => '#2563eb',
            'url'      => '/accounting',
            'menu'     => [
                ['icon' => '📊', 'label_en' => 'Dashboard', 'label_bn' => 'ড্যাশবোর্ড', 'url' => '/accounting'],
                ['icon' => '📋', 'label_en' => 'Chart of Accounts', 'label_bn' => 'চার্ট অব অ্যাকাউন্টস', 'url' => '/accounting/chart-of-accounts'],
                ['icon' => '📝', 'label_en' => 'Journal Entries', 'label_bn' => 'জার্নাল এন্ট্রি', 'url' => '/accounting/journal'],
                ['icon' => '📈', 'label_en' => 'General Ledger', 'label_bn' => 'লেজার বুক', 'url' => '/accounting/ledger'],
                ['icon' => '⚖️', 'label_en' => 'Trial Balance', 'label_bn' => 'ট্রায়াল ব্যালেন্স', 'url' => '/accounting/trial-balance'],
                ['icon' => '💰', 'label_en' => 'Income Statement', 'label_bn' => 'আয় বিবরণী', 'url' => '/accounting/income-statement'],
                ['icon' => '🏦', 'label_en' => 'Balance Sheet', 'label_bn' => 'ব্যালেন্স শিট', 'url' => '/accounting/balance-sheet'],
                ['icon' => '📦', 'label_en' => 'VAT / Tax Audit', 'label_bn' => 'ভ্যাট / ট্যাক্স রিপোর্ট', 'url' => '/accounting/tax-report'],
            ],
        ],
        'billing' => [
            'name'     => 'Billing',
            'name_bn'  => 'বিলিং',
            'icon'     => '💳',
            'color'    => '#7c3aed',
            'url'      => '/billing',
            'menu'     => [
                ['icon' => '💳', 'label_en' => 'Dashboard', 'label_bn' => 'ড্যাশবোর্ড', 'url' => '/billing'],
                ['icon' => '📄', 'label_en' => 'Invoices Register', 'label_bn' => 'ইনভয়েস তালিকা', 'url' => '/billing/invoices'],
                ['icon' => '🔁', 'label_en' => 'Subscriptions', 'label_bn' => 'সাবস্ক্রিপশন', 'url' => '/billing/subscriptions'],
                ['icon' => '📦', 'label_en' => 'Tier Plans', 'label_bn' => 'প্ল্যান সমূহ', 'url' => '/billing/plans'],
                ['icon' => '💵', 'label_en' => 'Payment Logs', 'label_bn' => 'পেমেন্ট হিস্টোরি', 'url' => '/billing/payments'],
                ['icon' => '⚠️', 'label_en' => 'Overdue Debt', 'label_bn' => 'বকেয়া ও ড্যু', 'url' => '/billing/overdue'],
            ],
        ],
        'crm' => [
            'name'     => 'CRM',
            'name_bn'  => 'সিআরএম',
            'icon'     => '👥',
            'color'    => '#059669',
            'url'      => '/crm',
            'menu'     => [
                ['icon' => '👥', 'label_en' => 'Dashboard', 'label_bn' => 'ড্যাশবোর্ড', 'url' => '/crm'],
                ['icon' => '🎯', 'label_en' => 'Lead Management', 'label_bn' => 'লিড ম্যানেজমেন্ট', 'url' => '/crm/leads'],
                ['icon' => '💼', 'label_en' => 'Deal Pipeline', 'label_bn' => 'ডিল পাইপলাইন', 'url' => '/crm/deals'],
                ['icon' => '🏢', 'label_en' => 'Corporate Accounts', 'label_bn' => 'কোম্পানি ডিরেক্টরি', 'url' => '/crm/companies'],
                ['icon' => '📞', 'label_en' => 'Contacts Directory', 'label_bn' => 'কনটাক্ট ম্যানেজার', 'url' => '/crm/contacts'],
                ['icon' => '🎫', 'label_en' => 'Support Tickets', 'label_bn' => 'সাপোর্ট টিকিট', 'url' => '/crm/tickets'],
                ['icon' => '📊', 'label_en' => 'Sales Analytics', 'label_bn' => 'সেলস অ্যানালিটিকস', 'url' => '/crm/analytics'],
            ],
        ],
        'commerce' => [
            'name'     => 'Commerce',
            'name_bn'  => 'কমার্স',
            'icon'     => '🛍️',
            'color'    => '#d97706',
            'url'      => '/commerce',
            'menu'     => [
                ['icon' => '🛍️', 'label_en' => 'Dashboard', 'label_bn' => 'ড্যাশবোর্ড', 'url' => '/commerce'],
                ['icon' => '📋', 'label_en' => 'Order Management', 'label_bn' => 'অর্ডার ম্যানেজমেন্ট', 'url' => '/commerce/orders'],
                ['icon' => '📦', 'label_en' => 'Product Catalog', 'label_bn' => 'প্রোডাক্ট ক্যাটালগ', 'url' => '/commerce/products'],
                ['icon' => '👤', 'label_en' => 'Customer Register', 'label_bn' => 'গ্রাহক তালিকা', 'url' => '/commerce/customers'],
                ['icon' => '💳', 'label_en' => 'Payment Gateways', 'label_bn' => 'পেমেন্ট গেটওয়ে', 'url' => '/commerce/payments'],
                ['icon' => '🏷️', 'label_en' => 'Coupons & Promos', 'label_bn' => 'কুপন ও ছাড়', 'url' => '/commerce/coupons'],
                ['icon' => '📊', 'label_en' => 'Sales Reports', 'label_bn' => 'সেলস রিপোর্ট', 'url' => '/commerce/reports'],
            ],
        ],
        'hrm' => [
            'name'     => 'HRM',
            'name_bn'  => 'এইচআরএম',
            'icon'     => '👔',
            'color'    => '#dc2626',
            'url'      => '/hrm',
            'menu'     => [
                ['icon' => '👔', 'label_en' => 'Dashboard', 'label_bn' => 'ড্যাশবোর্ড', 'url' => '/hrm'],
                ['icon' => '👤', 'label_en' => 'Employee Roster', 'label_bn' => 'কর্মচারী তালিকা', 'url' => '/hrm/employees'],
                ['icon' => '🏢', 'label_en' => 'Departments', 'label_bn' => 'বিভাগ ব্যবস্থাপনা', 'url' => '/hrm/departments'],
                ['icon' => '⏰', 'label_en' => 'Attendance Log', 'label_bn' => 'উপস্থিতি ট্র্যাকিং', 'url' => '/hrm/attendance'],
                ['icon' => '📅', 'label_en' => 'Leave Management', 'label_bn' => 'ছুটির ব্যবস্থাপনা', 'url' => '/hrm/leave'],
                ['icon' => '💰', 'label_en' => 'Payroll Sheet', 'label_bn' => 'বেতন ব্যবস্থাপনা', 'url' => '/hrm/payroll'],
                ['icon' => '📊', 'label_en' => 'Performance Reviews', 'label_bn' => 'পারফরম্যান্স রিভিউ', 'url' => '/hrm/performance'],
            ],
        ],
        'inventory' => [
            'name'     => 'Inventory',
            'name_bn'  => 'ইনভেন্টরি',
            'icon'     => '📦',
            'color'    => '#0891b2',
            'url'      => '/inventory',
            'menu'     => [
                ['icon' => '📦', 'label_en' => 'Dashboard', 'label_bn' => 'ড্যাশবোর্ড', 'url' => '/inventory'],
                ['icon' => '📋', 'label_en' => 'Stock Items Catalog', 'label_bn' => 'পণ্য তালিকা', 'url' => '/inventory/products'],
                ['icon' => '🏭', 'label_en' => 'Warehouses Hub', 'label_bn' => 'গুদামঘর', 'url' => '/inventory/warehouses'],
                ['icon' => '📥', 'label_en' => 'Stock In (GRN)', 'label_bn' => 'স্টক ইন (আগমন)', 'url' => '/inventory/stock-in'],
                ['icon' => '📤', 'label_en' => 'Stock Out (GIN)', 'label_bn' => 'স্টক আউট (নির্গমন)', 'url' => '/inventory/stock-out'],
                ['icon' => '⚠️', 'label_en' => 'Low Stock Alerts', 'label_bn' => 'লো-স্টক অ্যালার্ট', 'url' => '/inventory/low-stock'],
                ['icon' => '📊', 'label_en' => 'Stock Valuation', 'label_bn' => 'স্টক ভ্যালুয়েশন', 'url' => '/inventory/valuation'],
            ],
        ],
        'system' => [
            'name'     => 'System',
            'name_bn'  => 'সিস্টেম',
            'icon'     => '⚙️',
            'color'    => '#64748b',
            'url'      => '/system/status',
            'menu'     => [
                ['icon' => '⚙️', 'label_en' => 'Dashboard', 'label_bn' => 'ড্যাশবোর্ড', 'url' => '/system/status'],
                ['icon' => '📦', 'label_en' => 'Modules Inspector', 'label_bn' => 'মডিউল ইনস্পেক্টর', 'url' => '/system/modules'],
                ['icon' => '🩺', 'label_en' => 'System Health', 'label_bn' => 'সিস্টেম হেলথ', 'url' => '/health'],
            ],
        ],
    ];

    /**
     * Wrap module body content in the shared NetFusion layout.
     */
    public static function wrap(
        string $moduleKey,
        string $pageTitle,
        string $bodyHtml,
        string $extraCss = '',
        string $extraJs = '',
        string $currentUrl = '',
    ): string {
        $module   = self::$moduleRegistry[$moduleKey] ?? self::$moduleRegistry['system'];
        $version  = app()->version();
        $settings = SiteSettingsHelper::get();
        $locale   = SiteSettingsHelper::resolveLocale();
        $isBn     = ($locale === 'bn');

        $color    = $module['color'];
        $iconStr  = $module['icon'];
        $modName  = htmlspecialchars($isBn ? ($module['name_bn'] ?? $module['name']) : $module['name']);
        $current  = $currentUrl ?: $module['url'];

        // Brand & Logo
        $siteName    = htmlspecialchars($settings['site_name']);
        $siteTagline = htmlspecialchars($settings['site_tagline']);
        $logoIcon    = htmlspecialchars($settings['logo_icon']);
        $logoUrl     = htmlspecialchars($settings['logo_url']);
        $faviconUrl  = htmlspecialchars($settings['favicon_url']);

        $logoHtml = !empty($logoUrl)
            ? sprintf('<img src="%s" alt="%s" style="height:32px; width:auto; border-radius:6px;">', $logoUrl, $siteName)
            : sprintf('<div class="brand-icon">%s</div>', $logoIcon);

        $faviconTag = !empty($faviconUrl)
            ? sprintf('<link rel="icon" href="%s">', $faviconUrl)
            : '<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚡</text></svg>">';

        // Language Switcher Styles & Links
        $enActive = !$isBn ? 'background:#2563eb; color:#ffffff;' : 'color:#475569;';
        $bnActive = $isBn ? 'background:#2563eb; color:#ffffff;' : 'color:#475569;';

        // Build sidebar nav items
        $navHtml = '';
        foreach ($module['menu'] as $item) {
            $isActive = ($item['url'] === $current) ? 'active' : '';
            $itemLabel = $isBn ? ($item['label_bn'] ?? $item['label_en']) : $item['label_en'];
            $navHtml .= sprintf(
                '<a href="%s" class="nav-item %s"><span class="nav-icon">%s</span> %s</a>',
                htmlspecialchars($item['url']),
                $isActive,
                $item['icon'],
                htmlspecialchars($itemLabel)
            );
        }

        // Build cross-module quick links
        $crossModuleHtml = '';
        $crossLinks = [
            ['icon' => '📊', 'label_en' => 'Accounting', 'label_bn' => 'অ্যাকাউন্টিং', 'url' => '/accounting'],
            ['icon' => '💳', 'label_en' => 'Billing', 'label_bn' => 'বিলিং', 'url' => '/billing'],
            ['icon' => '👥', 'label_en' => 'CRM', 'label_bn' => 'সিআরএম', 'url' => '/crm'],
            ['icon' => '🛍️', 'label_en' => 'Commerce', 'label_bn' => 'কমার্স', 'url' => '/commerce'],
            ['icon' => '👔', 'label_en' => 'HRM', 'label_bn' => 'এইচআরএম', 'url' => '/hrm'],
            ['icon' => '📦', 'label_en' => 'Inventory', 'label_bn' => 'ইনভেন্টরি', 'url' => '/inventory'],
            ['icon' => '⚙️', 'label_en' => 'System', 'label_bn' => 'সিস্টেম', 'url' => '/system/status'],
        ];
        foreach ($crossLinks as $cl) {
            if ($cl['label_en'] === $module['name']) {
                continue; // Skip current
            }
            $clLabel = $isBn ? $cl['label_bn'] : $cl['label_en'];
            $crossModuleHtml .= sprintf(
                '<a href="%s" class="cross-module-link"><span class="nav-icon">%s</span> %s</a>',
                htmlspecialchars($cl['url']),
                $cl['icon'],
                htmlspecialchars($clLabel)
            );
        }

        $menuLabelModule = $isBn ? "মডিউল মেন্যু ({$modName})" : "Module Navigation ({$modName})";
        $menuLabelOther  = $isBn ? "অন্যান্য মডিউলস" : "Other Modules";
        $dashboardLabel  = $isBn ? "মূল ড্যাশবোর্ড" : "Main Dashboard";
        $healthLabel     = $isBn ? "সিস্টেম হেলথ" : "System Health";

        return <<<HTML
<!DOCTYPE html>
<html lang="{$locale}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$pageTitle} — {$modName} | {$siteName}</title>
    {$faviconTag}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            --warning: #f59e0b;
            --danger: #ef4444;
            --sidebar-width: 260px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', 'Hind Siliguri', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-body);
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-surface);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; bottom: 0; left: 0;
            z-index: 100;
        }
        .sidebar-brand {
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--border-color);
            text-decoration: none;
            color: inherit;
        }
        .brand-icon {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .brand-title { font-size: 16px; font-weight: 700; color: var(--text-heading); line-height: 1.1; }
        .brand-sub { font-size: 11px; color: var(--text-muted); font-weight: 500; }

        .module-badge-bar {
            padding: 12px 18px;
            background: var(--primary-light);
            border-bottom: 1px solid color-mix(in srgb, var(--primary) 20%, transparent);
            display: flex; align-items: center; gap: 10px;
        }
        .module-badge-icon { font-size: 20px; }
        .module-badge-text strong { display: block; font-size: 13.5px; font-weight: 700; color: var(--primary); }
        .module-badge-text span { font-size: 11px; color: var(--text-muted); }

        .sidebar-scroll { flex: 1; overflow-y: auto; padding: 14px 10px; }
        .menu-label {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            color: #94a3b8; letter-spacing: 0.5px; padding: 8px 10px 4px 10px;
        }
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 12px; color: #475569; text-decoration: none;
            font-size: 13px; font-weight: 500; border-radius: 7px;
            margin-bottom: 2px; transition: all 0.15s ease;
        }
        .nav-item:hover { background: #f1f5f9; color: var(--text-heading); }
        .nav-item.active { background: var(--primary-light); color: var(--primary); font-weight: 600; }
        .nav-icon { font-size: 15px; width: 18px; text-align: center; }

        .sidebar-divider { height: 1px; background: var(--border-color); margin: 10px 0; }
        .cross-module-link {
            display: flex; align-items: center; gap: 8px;
            padding: 6px 12px; color: #64748b; text-decoration: none;
            font-size: 12.5px; font-weight: 500; border-radius: 6px;
            transition: all 0.15s ease;
        }
        .cross-module-link:hover { background: #f8fafc; color: var(--text-heading); }

        .sidebar-bottom {
            padding: 14px 18px;
            border-top: 1px solid var(--border-color);
            background: #fafafa;
            font-size: 11.5px; color: var(--text-muted);
            text-align: center;
        }

        /* WORKSPACE */
        .workspace {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .top-header {
            height: 60px;
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border-color);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 28px; position: sticky; top: 0; z-index: 90;
        }
        .header-breadcrumb {
            display: flex; align-items: center; gap: 8px; font-size: 13.5px;
        }
        .breadcrumb-sep { color: #cbd5e1; font-size: 11px; }

        .header-actions { display: flex; align-items: center; gap: 14px; }
        .lang-switch {
            display: flex; align-items: center; background: #f1f5f9;
            padding: 3px; border-radius: 20px; font-size: 11.5px;
        }
        .lang-btn {
            padding: 3px 9px; border-radius: 14px; text-decoration: none;
            font-weight: 600; transition: all 0.15s ease;
        }

        .content-body { padding: 28px; flex: 1; }

        /* KPI & CARDS */
        .kpi-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 18px; margin-bottom: 24px;
        }
        .kpi-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 10px; padding: 18px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .kpi-label { font-size: 12.5px; color: var(--text-muted); font-weight: 600; margin-bottom: 5px; }
        .kpi-value { font-size: 24px; font-weight: 700; color: var(--text-heading); }
        .kpi-subtext { font-size: 11.5px; color: var(--text-muted); margin-top: 5px; }

        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 10px; overflow: hidden;
            margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .card-header {
            padding: 14px 20px; border-bottom: 1px solid var(--border-color);
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-title { font-size: 14.5px; font-weight: 700; color: var(--text-heading); }

        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f8fafc; padding: 11px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid var(--border-color); }
        td { padding: 11px 16px; border-bottom: 1px solid #f1f5f9; color: var(--text-body); }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background-color: #f8fafc; }

        .badge {
            display: inline-block; padding: 3px 8px; border-radius: 12px;
            font-size: 11px; font-weight: 600;
        }
        .badge-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .badge-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .badge-danger  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-info    { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 6px; font-size: 12.5px;
            font-weight: 600; cursor: pointer; text-decoration: none;
            transition: all 0.2s ease; border: none;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary { background: var(--bg-surface); color: #475569; border: 1px solid var(--border-color); }
        .btn-secondary:hover { background: #f8fafc; color: var(--text-heading); }

        .progress-bar-bg { width: 100%; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; }
        .progress-bar-fill { height: 100%; border-radius: 3px; }

        /* FOOTER */
        .app-footer {
            padding: 16px 28px; border-top: 1px solid var(--border-color);
            background: var(--bg-surface); display: flex;
            justify-content: space-between; align-items: center;
            font-size: 12.5px; color: var(--text-muted);
            flex-wrap: wrap; gap: 10px;
        }
        .footer-links { display: flex; gap: 16px; }
        .footer-links a { color: var(--text-muted); text-decoration: none; font-weight: 500; }
        .footer-links a:hover { color: var(--primary); }

        {$extraCss}
    </style>
</head>
<body>

    <!-- ══ SIDEBAR ══ -->
    <aside class="sidebar">
        <a href="/admin/dashboard" class="sidebar-brand">
            {$logoHtml}
            <div class="brand-text">
                <div class="brand-title">{$siteName}</div>
                <div class="brand-sub">{$siteTagline}</div>
            </div>
        </a>

        <div class="module-badge-bar">
            <div class="module-badge-icon">{$iconStr}</div>
            <div class="module-badge-text">
                <strong>{$modName}</strong>
                <span>Enterprise Suite</span>
            </div>
        </div>

        <div class="sidebar-scroll">
            <div class="menu-label">{$menuLabelModule}</div>
            {$navHtml}

            <div class="sidebar-divider"></div>

            <div class="menu-label">{$menuLabelOther}</div>
            {$crossModuleHtml}
            <a href="/admin/dashboard" class="cross-module-link" style="margin-top:6px; border-top: 1px solid var(--border-color); padding-top: 8px;">
                <span class="nav-icon">🏠</span> {$dashboardLabel}
            </a>
        </div>

        <div class="sidebar-bottom">
            <div>{$siteName}</div>
            <div style="margin-top:2px; font-size:10px;">&copy; {$settings['footer_company']} &bull; All Rights Reserved</div>
        </div>
    </aside>

    <!-- ══ WORKSPACE ══ -->
    <div class="workspace">
        <header class="top-header">
            <div class="header-breadcrumb">
                <a href="/admin/dashboard" style="color:var(--text-muted); text-decoration:none;">🏠 {$dashboardLabel}</a>
                <span class="breadcrumb-sep">›</span>
                <a href="{$module['url']}" style="color:var(--text-muted); text-decoration:none;">{$modName}</a>
                <span class="breadcrumb-sep">›</span>
                <span style="font-weight:600; color:var(--text-heading);">{$pageTitle}</span>
            </div>

            <div class="header-actions">
                <div class="lang-switch">
                    <a href="?lang=en" class="lang-btn" style="{$enActive}">EN</a>
                    <a href="?lang=bn" class="lang-btn" style="{$bnActive}">বাং</a>
                </div>
                <a href="/admin/dashboard" class="btn btn-secondary">⚡ {$dashboardLabel}</a>
            </div>
        </header>

        <main class="content-body">
            {$bodyHtml}
        </main>

        <!-- FOOTER: Version strictly displayed here as v1.3.5 -->
        <footer class="app-footer">
            <div>
                <strong>{$siteName}</strong> &bull; v{$version} &bull; {$settings['footer_copyright']}
            </div>
            <div class="footer-links">
                <a href="/admin/dashboard">{$dashboardLabel}</a>
                <a href="/health">{$healthLabel}</a>
                <a href="{$settings['footer_website']}" target="_blank">{$settings['footer_company']}</a>
            </div>
        </footer>
    </div>

    {$extraJs}
</body>
</html>
HTML;
    }

    /**
     * Helpers for HTML snippets in controllers
     */
    public static function kpiCard(string $label, string $value, string $subtext = '', string $color = ''): string
    {
        $style = $color ? "border-left: 4px solid {$color};" : '';
        $valColor = $color ? "color: {$color};" : '';
        return sprintf(
            '<div class="kpi-card" style="%s">
                <div class="kpi-label">%s</div>
                <div class="kpi-value" style="%s">%s</div>
                %s
            </div>',
            $style,
            htmlspecialchars($label),
            $valColor,
            htmlspecialchars($value),
            $subtext ? '<div class="kpi-subtext">' . htmlspecialchars($subtext) . '</div>' : ''
        );
    }

    public static function currency(float $amount, string $symbol = '৳'): string
    {
        return $symbol . ' ' . number_format($amount, 2);
    }

    public static function badge(string $text, string $type = 'success'): string
    {
        return sprintf('<span class="badge badge-%s">%s</span>', htmlspecialchars($type), htmlspecialchars($text));
    }
}
