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
use NetFusion\Core\Vector\VectorEngineManager;

class RAGQueryCommand extends Command
{
    protected string $name = 'rag:query';
    protected string $signature = 'rag:query {collection : Knowledge collection} {query : Question / prompt} {--k=3 : Top context chunks}';
    protected string $description = 'Query knowledge collection via Retrieval-Augmented Generation (RAG) pipeline';

    public function handle(): int
    {
        /** @var VectorEngineManager $manager */
        $manager = function_exists('vector') ? vector() : (function_exists('app') && app()->has('vector') ? app('vector') : new VectorEngineManager());

        $collectionName = (string)($this->argument(0) ?? 'default');
        $query = (string)($this->argument(1) ?? '');
        $topK = (int)$this->option('k', 3);

        $rag = $manager->rag();
        $response = $rag->query($query, $collectionName, ['top_k' => $topK]);

        $this->info("==========================================================================================");
        $this->info("                 RAG Knowledge Query Response ({$response->getExecutionTimeMs()}ms)");
        $this->info("                 Collection: {$collectionName} | Context Chunks: {$response->getContext()->count()}");
        $this->info("==========================================================================================");
        $this->line("");
        $this->info("Question: " . $query);
        $this->line("");
        $this->line($response->getAnswer());
        $this->line("");

        $this->info("Sources / Citations:");
        foreach ($response->getContext()->getChunks() as $i => $chunk) {
            $num = $i + 1;
            $this->line("  [#{$num}] ID: {$chunk['id']} (Relevance: " . round((float)$chunk['score'], 4) . ")");
        }

        return 0;
    }
}
