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
 * CLI command to ingest documents or text chunks into GraphRAG.
 * Usage: php netfusion graphrag:ingest --doc=manual_v1 --text="NetFusion is an enterprise PHP framework."
 */
class GraphRagIngestCommand extends Command
{
    protected string $name = 'graphrag:ingest';
    protected string $description = 'Ingest and chunk text/document into Multi-Modal Vector Knowledge Graph';

    public function handle(): int
    {
        /** @var GraphRagManager $manager */
        $manager = function_exists('app') && app()->has('graph_rag') ? app('graph_rag') : new GraphRagManager();

        $docId = $this->option('doc') ?? 'doc_' . time();
        $text = $this->option('text');
        $file = $this->option('file');

        if ($file !== null && file_exists($file)) {
            $text = file_get_contents($file);
        }

        if ($text === null || trim($text) === '') {
            $this->error("Provide either --text=\"...\" or --file=\"path/to/file\".");
            return 1;
        }

        $nodes = $manager->ingestDocument($docId, $text);
        $this->info("Successfully ingested document [{$docId}] into GraphRAG.");
        $this->line("Created " . count($nodes) . " semantic chunk nodes with entity relationship links.");

        return 0;
    }
}
