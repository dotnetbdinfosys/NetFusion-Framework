<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\GraphRag\GraphRagManager;

/**
 * CLI command to inspect GraphRAG topology, node counts, and edge distribution.
 * Usage: php netfusion graphrag:stats
 */
class GraphRagStatsCommand extends Command
{
    protected string $name = 'graphrag:stats';
    protected string $description = 'Display Multi-Modal Vector Knowledge Graph statistics, node and edge counts';

    public function handle(): int
    {
        /** @var GraphRagManager $manager */
        $manager = function_exists('app') && app()->has('graph_rag') ? app('graph_rag') : new GraphRagManager();
        $graph = $manager->getGraph();

        $this->info("=== Multi-Modal Vector Knowledge Graph (GraphRAG) Statistics ===");
        $this->line("Total Knowledge Nodes : " . $graph->countNodes());
        $this->line("Total Semantic Edges  : " . $graph->countEdges());
        $this->line("BM25 Inverted Docs    : " . $graph->getBm25Index()->count());

        return 0;
    }
}
