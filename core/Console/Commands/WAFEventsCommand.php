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

use NetFusion\Core\Console\Command;
use NetFusion\Core\WAF\WAFManager;

class WAFEventsCommand extends Command
{
    protected string $name = 'waf:events';
    protected string $signature = 'waf:events {--limit=20 : Maximum events to display}';
    protected string $description = 'Display recent WAF threat events log and attack forensics';

    public function handle(): int
    {
        /** @var WAFManager $waf */
        $waf = function_exists('waf') ? waf() : (function_exists('app') && app()->has('waf') ? app('waf') : new WAFManager());

        $limit = (int)$this->option('limit', 20);
        $events = $waf->getStore()->getRecentThreatEvents($limit);

        $this->info("==========================================================================================");
        $this->info("                 Recent WAF Threat Events & Forensics Log (Top {$limit})");
        $this->info("==========================================================================================");

        if (empty($events)) {
            $this->line("No threat events logged in current storage.");
            return 0;
        }

        $tableData = [];
        foreach ($events as $event) {
            $tableData[] = [
                $event['ip'] ?? 'unknown',
                $event['method'] ?? 'GET',
                mb_strimwidth((string)($event['uri'] ?? '/'), 0, 30, '...'),
                $event['action'] ?? 'BLOCK',
                $event['rule_id'] ?? 'N/A',
                (string)($event['anomaly_score'] ?? 0.0),
                date('Y-m-d H:i:s', (int)($event['created_at'] ?? $event['timestamp'] ?? time())),
            ];
        }

        $this->table(['Client IP', 'Method', 'URI', 'Action', 'Rule ID', 'Score', 'Timestamp'], $tableData);
        return 0;
    }
}
