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
 * CLI command to execute Tri-Way hybrid retrieval and context synthesis.
 * Usage: php netfusion graphrag:query --q="architecture" [--limit=3] [--synthesize]
 */
class GraphRagQueryCommand extends Command
{
    protected string $name = 'graphrag:query';
    protected string $description = 'Query GraphRAG via Tri-Way Hybrid Retrieval (Vector + BM25 + Graph Traversal)';

    public function handle(): int
    {
        /** @var GraphRagManager $manager */
        $manager = function_exists('app') && app()->has('graph_rag') ? app('graph_rag') : new GraphRagManager();

        $query = $this->option('q');
        if ($query === null || trim($query) === '') {
            $this->error("Provide a search query via --q=\"...\".");
            return 1;
        }

        $limit = (int) ($this->option('limit') ?? 5);

        if ($this->hasOption('synthesize') && $this->option('synthesize')) {
            $context = $manager->synthesizeContext($query, limit: $limit);
            $this->info("=== Synthesized LLM Grounded Context ===");
            $this->line($context);
            return 0;
        }

        $results = $manager->retrieve($query, limit: $limit, rerank: true);
        $this->info("Found " . count($results) . " relevant knowledge nodes for query: '{$query}'");

        foreach ($results as $item) {
            $node = $item['node'];
            $score = $item['rerankScore'] ?? $item['score'] ?? 0.0;
            $this->line(sprintf(" - [%s] ID: %s (Score: %.4f) Content: %s", $node->getLabel(), $node->getId(), $score, mb_substr($node->getContent(), 0, 80)));
        }

        return 0;
    }
}
