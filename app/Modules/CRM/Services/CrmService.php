<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/CRM/Services/CrmService.php
| Module    : CRM
| Namespace : NetFusion\Modules\CRM\Services
| Purpose   : Service managing CRM leads, deals and sales pipeline stages.
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

namespace NetFusion\Modules\CRM\Services;

use NetFusion\Core\Application;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Modules\CRM\Models\Deal;
use NetFusion\Modules\CRM\Models\Lead;
use RuntimeException;

class CrmService
{
    public function __construct(
        protected Application $app
    ) {}

    protected function getDb(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection();
    }

    public function createLead(array $data): Lead
    {
        $db = $this->getDb();
        $now = date('Y-m-d H:i:s');

        $id = (int)$db->insert("
            INSERT INTO crm_leads (tenant_id, name, company, email, phone, status, source, assigned_to, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $data['tenant_id'] ?? null,
            $data['name'],
            $data['company'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['status'] ?? 'new',
            $data['source'] ?? 'website',
            $data['assigned_to'] ?? null,
            $now,
            $now,
        ]);

        $row = Lead::where('id', '=', $id)->first();
        return Lead::newFromBuilder($row);
    }

    public function updateLeadStatus(int $id, string $status): Lead
    {
        $db = $this->getDb();
        $now = date('Y-m-d H:i:s');

        $db->statement("
            UPDATE crm_leads
            SET status = ?, updated_at = ?
            WHERE id = ?
        ", [$status, $now, $id]);

        $row = Lead::where('id', '=', $id)->first();
        if (!$row) {
            throw new RuntimeException("Lead #{$id} not found.");
        }

        return Lead::newFromBuilder($row);
    }

    public function createDeal(array $data): Deal
    {
        $db = $this->getDb();
        $now = date('Y-m-d H:i:s');

        $id = (int)$db->insert("
            INSERT INTO crm_deals (tenant_id, lead_id, title, amount, stage, expected_close_date, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $data['tenant_id'] ?? null,
            $data['lead_id'] ?? null,
            $data['title'],
            $data['amount'] ?? 0.0,
            $data['stage'] ?? 'prospect',
            $data['expected_close_date'] ?? null,
            $now,
            $now,
        ]);

        $row = Deal::where('id', '=', $id)->first();
        return Deal::newFromBuilder($row);
    }

    public function updateDealStage(int $id, string $stage): Deal
    {
        $db = $this->getDb();
        $now = date('Y-m-d H:i:s');

        $db->statement("
            UPDATE crm_deals
            SET stage = ?, updated_at = ?
            WHERE id = ?
        ", [$stage, $now, $id]);

        $row = Deal::where('id', '=', $id)->first();
        if (!$row) {
            throw new RuntimeException("Deal #{$id} not found.");
        }

        return Deal::newFromBuilder($row);
    }

    public function getPipelineSummary(): array
    {
        $db = $this->getDb();
        $stages = ['prospect', 'proposal', 'negotiation', 'won', 'lost'];
        $summary = [];

        foreach ($stages as $stage) {
            $deals = Deal::where('stage', '=', $stage)->get();
            $count = count($deals);
            $totalAmount = 0.0;
            foreach ($deals as $d) {
                $totalAmount += (float)($d['amount'] ?? 0.0);
            }
            $summary[$stage] = [
                'count' => $count,
                'total_amount' => $totalAmount,
            ];
        }

        return $summary;
    }

    public function listLeads(int $limit = 50, int $offset = 0): array
    {
        $rows = Lead::query()->limit($limit)->offset($offset)->get();
        return array_map(fn($r) => Lead::newFromBuilder($r), $rows);
    }

    public function listDeals(int $limit = 50, int $offset = 0): array
    {
        $rows = Deal::query()->limit($limit)->offset($offset)->get();
        return array_map(fn($r) => Deal::newFromBuilder($r), $rows);
    }
}
