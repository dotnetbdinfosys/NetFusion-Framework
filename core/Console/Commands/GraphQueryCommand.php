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
use NetFusion\Core\Graph\GraphManager;

class GraphQueryCommand extends Command
{
    protected string $name = 'graph:query';
    protected string $description = 'Execute a Cypher query against a graph store and display records';

    public function handle(): int
    {
        $cypher = $this->argument(0);
        $graphName = $this->argument(1) ?? 'default';

        if (empty($cypher)) {
            $this->error("Usage: php netfusion graph:query \"<cypher_query>\" [graph_name]");
            return 1;
        }

        /** @var GraphManager $manager */
        $manager = $this->app->make('graph');

        $this->info("Executing Cypher query on graph '{$graphName}'...");
        $result = $manager->query($cypher, graphName: $graphName);

        $this->line("Execution Time: {$result->getExecutionTimeMs()} ms");
        $this->line("Records Returned: {$result->count()}");

        if (!$result->isEmpty()) {
            $headers = $result->getColumns();
            $rows = [];
            foreach ($result->getRecords() as $rec) {
                $row = [];
                foreach ($headers as $col) {
                    $val = $rec[$col] ?? null;
                    $row[] = is_array($val) ? json_encode($val) : (string)$val;
                }
                $rows[] = $row;
            }
            $this->table($headers, $rows);
        } else {
            $this->comment("No records matched or query completed with stats:");
            foreach ($result->getStats() as $k => $v) {
                if ($v > 0) {
                    $this->line("  - {$k}: {$v}");
                }
            }
        }

        return 0;
    }
}
