<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\CEP\CEPManager;
use NetFusion\Core\Console\Command;

class CEPAlertsCommand extends Command
{
    protected string $name = 'cep:alerts';
    protected string $signature = 'cep:alerts {--rule= : Filter alerts by rule name} {--stream= : Filter by stream} {--limit=20 : Number of alerts to retrieve}';
    protected string $description = 'List recent complex event alerts, pattern anomalies, and triggered incident logs';

    public function handle(): int
    {
        /** @var CEPManager $manager */
        $manager = function_exists('cep') ? cep() : (function_exists('app') && app()->has('cep') ? app('cep') : new CEPManager());

        $ruleFilter = $this->option('rule') ? (string) $this->option('rule') : null;
        $streamFilter = $this->option('stream') ? (string) $this->option('stream') : null;
        $limit = (int) ($this->option('limit') ?: 20);

        $filter = [];
        if ($ruleFilter !== null) {
            $filter['rule_name'] = $ruleFilter;
        }
        if ($streamFilter !== null) {
            $filter['stream'] = $streamFilter;
        }

        $alerts = $manager->getStore()->getAlerts($filter, $limit);

        $this->info("==========================================================================================");
        $this->info("                 Recent CEP Alerts & Detected Pattern Anomalies");
        $this->info("==========================================================================================");

        if (empty($alerts)) {
            $this->line("No alerts recorded yet in the CEP store.");
            return 0;
        }

        $headers = ['Alert ID', 'Rule', 'Stream', 'Partition Key', 'Severity', 'Created At'];
        $rows = [];

        foreach ($alerts as $alert) {
            $rows[] = [
                substr((string) ($alert['id'] ?? 'N/A'), 0, 8) . '...',
                (string) ($alert['rule_name'] ?? 'N/A'),
                (string) ($alert['stream'] ?? 'N/A'),
                (string) ($alert['partition_key'] ?? 'global'),
                (string) ($alert['severity'] ?? 'INFO'),
                (string) ($alert['created_at'] ?? 'N/A'),
            ];
        }

        $this->table($headers, $rows);
        $this->line("Retrieved " . count($alerts) . " alerts (limit: {$limit}).");

        return 0;
    }
}
