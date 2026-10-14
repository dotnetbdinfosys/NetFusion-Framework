<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM & Helpdesk Dashboard — NetFusion Framework</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; padding-bottom: 1rem; margin-bottom: 2rem; }
        .badge { background: #6366f1; color: #fff; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 600; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: #1e293b; padding: 1.5rem; border-radius: 0.5rem; border: 1px solid #334155; }
        .stat-val { font-size: 2rem; font-weight: 700; color: #818cf8; margin-top: 0.5rem; }
        .pipeline-bar { display: flex; gap: 0.5rem; margin-bottom: 2rem; }
        .pipeline-stage { flex: 1; background: #1e293b; border: 1px solid #334155; border-radius: 0.5rem; padding: 1rem; text-align: center; }
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
                <h1 style="margin: 0; font-size: 1.75rem;">CRM & Customer Support Helpdesk</h1>
                <p style="margin: 0.5rem 0 0; color: #94a3b8;">Sales Lead Pipeline & SLA-Driven Customer Service</p>
            </div>
            <span class="badge">NetFusion HMVC</span>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div>Total Leads</div>
                <div class="stat-val"><?= htmlspecialchars((string)($leadsCount ?? 0)) ?></div>
            </div>
            <div class="stat-card">
                <div>Active Deals</div>
                <div class="stat-val"><?= htmlspecialchars((string)($dealsCount ?? 0)) ?></div>
            </div>
            <div class="stat-card">
                <div>Open Support Tickets</div>
                <div class="stat-val"><?= htmlspecialchars((string)($openTicketsCount ?? 0)) ?></div>
            </div>
        </div>

        <div style="font-weight: 600; margin-bottom: 0.75rem;">Sales Deal Pipeline</div>
        <div class="pipeline-bar">
            <?php foreach (($pipeline ?? []) as $stageName => $stageData): ?>
                <div class="pipeline-stage">
                    <div style="font-size: 0.85rem; color: #94a3b8; text-transform: uppercase;"><?= htmlspecialchars((string)$stageName) ?></div>
                    <div style="font-size: 1.25rem; font-weight: 700; color: #38bdf8; margin: 0.25rem 0;"><?= $stageData['count'] ?> deals</div>
                    <div style="font-size: 0.85rem; color: #64748b;">৳ <?= number_format((float)$stageData['total_amount'], 2) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="table-card">
            <div style="padding: 1rem 1.5rem; font-weight: 600; border-bottom: 1px solid #334155;">Recent Support Tickets</div>
            <table>
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Subject</th>
                        <th>Customer</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>SLA Due</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentTickets)): ?>
                        <tr><td colspan="6" style="text-align: center; color: #94a3b8;">No support tickets logged.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentTickets as $t): ?>
                            <tr>
                                <td><?= htmlspecialchars($t['ticket_number']) ?></td>
                                <td><?= htmlspecialchars($t['subject']) ?></td>
                                <td><?= htmlspecialchars($t['customer_name']) ?></td>
                                <td><span style="color: <?= $t['priority'] === 'urgent' ? '#f87171' : ($t['priority'] === 'high' ? '#fbbf24' : '#38bdf8') ?>;"><?= strtoupper(htmlspecialchars($t['priority'])) ?></span></td>
                                <td><?= ucfirst(htmlspecialchars($t['status'])) ?></td>
                                <td><?= htmlspecialchars($t['sla_due_at'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
