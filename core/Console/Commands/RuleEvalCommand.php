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
use NetFusion\Core\RuleEngine\Fact\Fact;
use NetFusion\Core\RuleEngine\RuleEngineManager;

class RuleEvalCommand extends Command
{
    protected string $name = 'rule:eval';
    protected string $signature = 'rule:eval {type : Fact type name} {payload? : JSON payload string for fact}';
    protected string $description = 'Assert a fact and trigger forward-chaining rule execution';

    public function handle(): int
    {
        /** @var RuleEngineManager $manager */
        $manager = function_exists('rule_engine') ? rule_engine() : (function_exists('app') && app()->has('rule_engine') ? app('rule_engine') : new RuleEngineManager());

        $type = (string) $this->argument('type');
        $rawPayload = (string) ($this->argument('payload') ?? '{}');
        $payload = json_decode($rawPayload, true);

        if (!is_array($payload)) {
            $this->error("Invalid JSON payload provided: {$rawPayload}");
            return 1;
        }

        $fact = new Fact($type, $payload);
        $manager->assertFact($fact);

        $this->info("Asserted Fact [{$type}] with ID [{$fact->getId()}]. Firing agenda...");

        $start = microtime(true);
        $firedCount = $manager->fireRules();
        $elapsedMs = round((microtime(true) - $start) * 1000.0, 2);

        $this->info("Fired {$firedCount} rule(s) in {$elapsedMs}ms.");
        return 0;
    }
}
