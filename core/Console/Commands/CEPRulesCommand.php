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

class CEPRulesCommand extends Command
{
    protected string $name = 'cep:rules';
    protected string $signature = 'cep:rules {--stream= : Filter rules by stream topic} {--enabled : Show only active rules}';
    protected string $description = 'List all registered Complex Event Processing (CEP) rules and execution statistics';

    public function handle(): int
    {
        /** @var CEPManager $manager */
        $manager = function_exists('cep') ? cep() : (function_exists('app') && app()->has('cep') ? app('cep') : new CEPManager());

        $streamFilter = $this->option('stream') ? (string) $this->option('stream') : null;
        $onlyEnabled = (bool) $this->option('enabled');

        $rules = $manager->getRules();

        $this->info("==========================================================================================");
        $this->info("                    Complex Event Processing (CEP) Registered Rules");
        $this->info("==========================================================================================");

        if (empty($rules)) {
            $this->line("No CEP rules currently registered.");
            return 0;
        }

        $headers = ['Rule Name', 'Stream Topic', 'Status', 'Processed', 'Matches', 'Alerts', 'Type'];
        $rows = [];

        foreach ($rules as $rule) {
            if ($onlyEnabled && !$rule->isEnabled()) {
                continue;
            }
            if ($streamFilter !== null && $rule->getStreamName() !== '*' && $rule->getStreamName() !== $streamFilter) {
                continue;
            }

            $stats = $rule->getStats();
            $type = ($stats['has_pattern'] ? 'Pattern' : '') . ($stats['has_window'] ? ' + Window' : '');
            if (empty(trim($type))) {
                $type = 'Filter/Direct';
            }

            $rows[] = [
                $stats['name'],
                $stats['stream'],
                $stats['enabled'] ? '<info>ENABLED</info>' : '<comment>DISABLED</comment>',
                (string) $stats['events_processed'],
                (string) $stats['matches_detected'],
                (string) $stats['alerts_fired'],
                trim($type, ' +'),
            ];
        }

        $this->table($headers, $rows);
        $this->line("Total Registered Rules: " . count($rules));

        return 0;
    }
}
