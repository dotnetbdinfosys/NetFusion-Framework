<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/HRM/Views/dashboard.php
| Module    : Modules\HRM
| Namespace : None (View Template)
| Purpose   : HTML view template for Enterprise HRM & Payroll Dashboard.
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
    <title>Enterprise HRM & Payroll Dashboard — NetFusion</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 30px; }
        .container { max-width: 1100px; margin: 0 auto; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; padding-bottom: 20px; margin-bottom: 30px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 13px; font-weight: 600; text-transform: uppercase; background: #1e3a8a; color: #60a5fa; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .stat-card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 20px; }
        .stat-val { font-size: 28px; font-weight: bold; color: #38bdf8; margin-top: 8px; }
        .table-card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 24px; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { border-bottom: 2px solid #334155; padding: 12px 8px; color: #94a3b8; font-size: 13px; text-transform: uppercase; }
        td { border-bottom: 1px solid #334155; padding: 12px 8px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-bar">
            <div>
                <h1>👥 Enterprise HRM & Payroll Dashboard</h1>
                <p style="color: #94a3b8; margin: 4px 0 0 0;">DotNetBD NetFusion Framework — Human Capital Management</p>
            </div>
            <div>
                <span class="badge">Tenant: <?= htmlspecialchars($tenantId ?? 'global') ?></span>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div style="color: #94a3b8; font-size: 14px;">Total Employees</div>
                <div class="stat-val"><?= (int) ($employeeCount ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div style="color: #94a3b8; font-size: 14px;">Total Departments</div>
                <div class="stat-val"><?= (int) ($departmentCount ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div style="color: #94a3b8; font-size: 14px;">Total Payrolls Processed</div>
                <div class="stat-val"><?= (int) ($payrollCount ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div style="color: #94a3b8; font-size: 14px;">Disbursed Net Salary</div>
                <div class="stat-val" style="color: #34d399;"><?= htmlspecialchars((string) ($currency ?? 'BDT')) ?> <?= number_format((float) ($totalDisbursed ?? 0.00), 2) ?></div>
            </div>
        </div>

        <div class="table-card">
            <h2>Recent Employees</h2>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Email</th>
                        <th>Basic Salary</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($employees)): ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($emp['employee_code']) ?></code></td>
                                <td><strong><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></strong></td>
                                <td><?= htmlspecialchars($emp['designation']) ?></td>
                                <td><?= htmlspecialchars($emp['email']) ?></td>
                                <td><?= htmlspecialchars((string) ($currency ?? 'BDT')) ?> <?= number_format((float) $emp['basic_salary'], 2) ?></td>
                                <td><span style="color: #34d399; font-weight: 600;"><?= htmlspecialchars(strtoupper($emp['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; color: #64748b; padding: 24px;">No employees found in tenant scope.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
