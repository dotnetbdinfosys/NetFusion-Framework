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

class CEPStatsCommand extends Command
{
    protected string $name = 'cep:stats';
    protected string $signature = 'cep:stats';
    protected string $description = 'Display real-time throughput, window metrics, and CEP engine runtime statistics';

    public function handle(): int
    {
        /** @var CEPManager $manager */
        $manager = function_exists('cep') ? cep() : (function_exists('app') && app()->has('cep') ? app('cep') : new CEPManager());

        $stats = $manager->getStats();

        $this->info("==========================================================================================");
        $this->info("                     CEP Streaming & Pattern Engine Runtime Stats");
        $this->info("==========================================================================================");
        $this->line("Engine Uptime          : {$stats['uptime_seconds']} seconds");
        $this->line("Total Events Ingested  : {$stats['total_events_ingested']}");
        $this->line("Total Alerts / Matches : {$stats['total_alerts_emitted']}");
        $this->line("Active Data Streams    : {$stats['active_streams']}");
        $this->line("Registered Rules       : {$stats['registered_rules']}");
        $this->line("Registered Sources     : {$stats['registered_sources']}");
        $this->line("------------------------------------------------------------------------------------------");

        if (!empty($stats['rules'])) {
            $headers = ['Rule', 'Stream', 'Processed', 'Matches', 'Alerts', 'Active Paths', 'Active Windows'];
            $rows = [];
            foreach ($stats['rules'] as $ruleStat) {
                $rows[] = [
                    $ruleStat['name'],
                    $ruleStat['stream'],
                    (string) $ruleStat['events_processed'],
                    (string) $ruleStat['matches_detected'],
                    (string) $ruleStat['alerts_fired'],
                    (string) ($ruleStat['active_nfa_paths'] ?? 0),
                    (string) ($ruleStat['active_windows'] ?? 0),
                ];
            }
            $this->table($headers, $rows);
        }

        return 0;
    }
}
