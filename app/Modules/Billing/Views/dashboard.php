<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Views/dashboard.php
| Module    : Modules\Billing
| Namespace : None (View Template)
| Purpose   : HMVC HTML view template for the SaaS Subscription & Billing Dashboard.
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaaS Subscription & Billing Dashboard — NetFusion</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 30px; }
        .container { max-width: 1100px; margin: 0 auto; }
        h1, h2, h3 { margin-top: 0; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; padding-bottom: 20px; margin-bottom: 30px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 13px; font-weight: 600; text-transform: uppercase; }
        .badge-active { background: #065f46; color: #34d399; }
        .badge-trialing { background: #854d0e; color: #facc15; }
        .badge-none { background: #334155; color: #94a3b8; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 40px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.2); }
        .card.featured { border-color: #38bdf8; position: relative; }
        .price { font-size: 32px; font-weight: 800; color: #38bdf8; margin: 16px 0; }
        .price span { font-size: 14px; font-weight: 400; color: #94a3b8; }
        ul.features { list-style: none; padding: 0; margin: 20px 0; }
        ul.features li { padding: 6px 0; color: #cbd5e1; font-size: 14px; }
        ul.features li::before { content: "✔ "; color: #34d399; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; background: #1e293b; border-radius: 8px; overflow: hidden; }
        th, td { padding: 14px 18px; text-align: left; border-bottom: 1px solid #334155; font-size: 14px; }
        th { background: #0f172a; color: #94a3b8; text-transform: uppercase; font-size: 12px; letter-spacing: 0.05em; }
        .footer { text-align: center; color: #64748b; font-size: 13px; margin-top: 60px; border-top: 1px solid #334155; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-bar">
            <div>
                <h1>SaaS Billing & Subscriptions</h1>
                <p style="color: #94a3b8; margin: 4px 0 0 0;">NetFusion HMVC Multi-Tenant Engine</p>
            </div>
            <div>
                <span style="color: #94a3b8; font-size: 14px; margin-right: 8px;">Active Tenant:</span>
                <span class="badge badge-active"><?= $this->e($tenantId) ?></span>
            </div>
        </div>

        <!-- Current Status -->
        <div class="card" style="margin-bottom: 30px;">
            <h2>Subscription Overview</h2>
            <?php if ($subscription): ?>
                <p>Status: 
                    <span class="badge badge-<?= $this->e($subscription->status) ?>">
                        <?= $this->e($subscription->status) ?>
                    </span>
                    <?php if ($subscription->onTrial()): ?>
                        <span style="color: #facc15; font-size: 13px; margin-left: 8px;">
                            (Trial ends: <?= $this->e($subscription->trial_ends_at) ?>)
                        </span>
                    <?php endif; ?>
                </p>
                <p>Active Plan ID: <strong><?= $this->e($subscription->plan_id) ?></strong></p>
                <p>Period End: <?= $this->e($subscription->current_period_end) ?></p>
            <?php else: ?>
                <p>Status: <span class="badge badge-none">No Active Subscription</span></p>
                <p style="color: #94a3b8;">Choose a plan below to activate your SaaS subscription.</p>
            <?php endif; ?>
        </div>

        <!-- Plans -->
        <h2>Available Subscription Tiers</h2>
        <div class="grid">
            <?php foreach ($plans as $plan): ?>
                <div class="card <?= $plan->slug === 'professional' ? 'featured' : '' ?>">
                    <h3><?= $this->e($plan->name) ?></h3>
                    <div class="price"><?= $this->e($currency) ?><?= number_format((float)$plan->price, 2) ?> <span>/ <?= $this->e($plan->billing_interval) ?></span></div>
                    <ul class="features">
                        <?php foreach ($plan->getFeaturesList() as $feature): ?>
                            <li><?= $this->e($feature) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Invoices -->
        <h2>Billing Invoices</h2>
        <?php if (!empty($invoices)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><strong><?= $this->e($invoice->invoice_number) ?></strong></td>
                            <td><?= $this->e($invoice->currency) ?> <?= number_format((float)$invoice->amount, 2) ?></td>
                            <td>
                                <span class="badge badge-<?= $invoice->isPaid() ? 'active' : 'trialing' ?>">
                                    <?= $this->e($invoice->status) ?>
                                </span>
                            </td>
                            <td><?= $this->e($invoice->created_at) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #94a3b8;">No invoices found for this tenant.</p>
        <?php endif; ?>

        <div class="footer">
            NetFusion Framework v<?= app()->version() ?> &bull; Author: Md. Atiquzzaman Ashish &bull; DotNetBD
        </div>
    </div>
</body>
</html>
