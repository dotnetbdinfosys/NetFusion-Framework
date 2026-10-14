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

class GraphTraverseCommand extends Command
{
    protected string $name = 'graph:traverse';
    protected string $description = 'Find the shortest path or traverse between two graph nodes';

    public function handle(): int
    {
        $startId = $this->argument(0);
        $targetId = $this->argument(1);
        $graphName = $this->argument(2) ?? 'default';

        if (empty($startId) || empty($targetId)) {
            $this->error("Usage: php netfusion graph:traverse <startNodeId> <targetNodeId> [graph_name]");
            return 1;
        }

        /** @var GraphManager $manager */
        $manager = $this->app->make('graph');

        $this->info("Finding shortest path from '{$startId}' to '{$targetId}' in graph '{$graphName}'...");
        $path = $manager->shortestPath($startId, $targetId, graphName: $graphName);

        if ($path === null) {
            $this->warn("No path found between '{$startId}' and '{$targetId}'.");
            return 0;
        }

        $this->line("Path Length (Hops): {$path->getLength()}");
        $this->line("Total Weighted Distance: {$path->getTotalWeight()}");

        $nodeChain = implode(' -> ', $path->getNodeIds());
        $this->line("Node Sequence: {$nodeChain}");

        return 0;
    }
}
