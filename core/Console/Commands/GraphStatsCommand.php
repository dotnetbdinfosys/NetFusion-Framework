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

class GraphStatsCommand extends Command
{
    protected string $name = 'graph:stats';
    protected string $description = 'Display graph database metrics, node and edge counts, and component clusters';

    public function handle(): int
    {
        $graphName = $this->argument(0) ?? 'default';

        /** @var GraphManager $manager */
        $manager = $this->app->make('graph');
        $store = $manager->graph($graphName);

        $this->info("==================================================");
        $this->info("  Enterprise Graph Database: '{$graphName}'");
        $this->info("==================================================");

        $nodeCount = $store->countNodes();
        $edgeCount = $store->countEdges();

        $this->line("Total Nodes: {$nodeCount}");
        $this->line("Total Edges: {$edgeCount}");

        if ($nodeCount > 0) {
            $components = $manager->connectedComponents($graphName);
            $this->line("Connected Components: " . count($components));

            $this->line("\nTop PageRank Influencers:");
            $pr = $manager->pageRank($graphName);
            $topPr = array_slice($pr, 0, 5, true);
            foreach ($topPr as $nodeId => $score) {
                $this->line(sprintf("  • %-20s: %.4f", $nodeId, $score));
            }
        }

        return 0;
    }
}
