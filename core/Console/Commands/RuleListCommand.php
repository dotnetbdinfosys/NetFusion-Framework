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

class RuleListCommand extends Command
{
    protected string $name = 'rule:list';
    protected string $signature = 'rule:list {--enabled : Show only enabled rules}';
    protected string $description = 'List all compiled business rules in the RETE network and persistence store';

    public function handle(): int
    {
        /** @var RuleEngineManager $manager */
        $manager = function_exists('rule_engine') ? rule_engine() : (function_exists('app') && app()->has('rule_engine') ? app('rule_engine') : new RuleEngineManager());

        $onlyEnabled = (bool) $this->option('enabled');
        $rules = $manager->getStore()->getAllRules();

        $this->info("==========================================================================================");
        $this->info("                 Business Rule Engine: Registered RETE Rules");
        $this->info("==========================================================================================");

        if (empty($rules)) {
            $this->line("No business rules currently registered in the engine.");
            return 0;
        }

        $headers = ['Rule Name', 'Salience', 'Patterns', 'Enabled', 'Description', 'Updated At'];
        $rows = [];

        foreach ($rules as $name => $rule) {
            $enabled = (bool) ($rule['enabled'] ?? true);
            if ($onlyEnabled && !$enabled) {
                continue;
            }

            $patterns = $rule['patterns'] ?? [];
            $patternTypes = implode(', ', array_map(fn($p) => $p['type'] ?? 'Unknown', $patterns));

            $rows[] = [
                $name,
                (string) ($rule['salience'] ?? 0),
                $patternTypes ?: 'None',
                $enabled ? '<info>ACTIVE</info>' : '<comment>DISABLED</comment>',
                $rule['description'] ?? '—',
                $rule['updated_at'] ?? '—',
            ];
        }

        $this->table($headers, $rows);
        return 0;
    }
}
