<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\DistributedDb\DistributedDbManager;

/**
 * Class DsqlQueryCommand
 *
 * CLI: php netfusion dsql:query "<sql_query>"
 */
class DsqlQueryCommand extends Command
{
    protected string $name = 'dsql:query';
    protected string $description = 'Plan and execute a distributed SQL query across partitioned shards';

    public function handle(array $args = []): int
    {
        $query = null;
        foreach ($args as $arg) {
            if (is_string($arg) && !str_starts_with($arg, '--')) {
                $query = $arg;
                break;
            }
        }

        $query ??= (string) $this->argument(0, 'SELECT * FROM users');

        /** @var DistributedDbManager $dbManager */
        $dbManager = app(DistributedDbManager::class);
        $plan = $dbManager->planner()->plan($query);

        $this->info("=== Distributed SQL Query Plan ===");
        $this->line("Target Table    : {$plan->table}");
        $this->line("Operation Type  : {$plan->type->value}");
        $this->line("Target Shards   : " . implode(', ', $plan->getTargetShardIds()));
        $this->line("Point Lookup    : " . ($plan->isPointLookup() ? 'YES' : 'NO'));
        $this->line("Scatter-Gather  : " . ($plan->isScatterGather ? 'YES' : 'NO'));
        $this->line("Pushdown SQL    : {$plan->pushdownSql}");
        $this->line("--------------------------------------------------");

        $results = $dbManager->executor()->execute($plan);
        $this->info("Results (" . count($results) . " rows):");
        $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }
}
