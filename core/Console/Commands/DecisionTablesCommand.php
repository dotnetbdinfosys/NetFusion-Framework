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
use NetFusion\Core\RuleEngine\RuleEngineManager;

class DecisionTablesCommand extends Command
{
    protected string $name = 'decision:tables';
    protected string $signature = 'decision:tables';
    protected string $description = 'List all registered DMN 1.3 Decision Tables and Hit Policies';

    public function handle(): int
    {
        /** @var RuleEngineManager $manager */
        $manager = function_exists('rule_engine') ? rule_engine() : (function_exists('app') && app()->has('rule_engine') ? app('rule_engine') : new RuleEngineManager());

        $tables = $manager->getStore()->getAllDecisionTables();

        $this->info("==========================================================================================");
        $this->info("                 DMN 1.3 Decision Tables: Registered Decision Models");
        $this->info("==========================================================================================");

        if (empty($tables)) {
            $this->line("No DMN decision tables currently registered in the engine.");
            return 0;
        }

        $headers = ['Table Name', 'Hit Policy', 'Inputs', 'Outputs', 'Rules Count', 'Updated At'];
        $rows = [];

        foreach ($tables as $name => $table) {
            $inputs = array_map(fn($i) => $i['expression'] ?? '', $table['inputs'] ?? []);
            $outputs = array_map(fn($o) => $o['name'] ?? '', $table['outputs'] ?? []);
            $rules = $table['rules'] ?? [];

            $rows[] = [
                $name,
                '<info>' . ($table['hit_policy'] ?? 'FIRST') . '</info>',
                implode(', ', $inputs) ?: '—',
                implode(', ', $outputs) ?: '—',
                (string) count($rules),
                $table['updated_at'] ?? '—',
            ];
        }

        $this->table($headers, $rows);
        return 0;
    }
}
