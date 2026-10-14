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

class VectorSearchCommand extends Command
{
    protected string $name = 'vector:search';
    protected string $signature = 'vector:search {collection : Collection name} {query : Natural language query} {--k=5 : Top-K results} {--type=hybrid : Search type (hybrid, dense, sparse)}';
    protected string $description = 'Perform semantic or hybrid vector search across a knowledge collection';

    public function handle(): int
    {
        /** @var VectorEngineManager $manager */
        $manager = function_exists('vector') ? vector() : (function_exists('app') && app()->has('vector') ? app('vector') : new VectorEngineManager());

        $collectionName = (string)($this->argument(0) ?? 'default');
        $query = (string)($this->argument(1) ?? '');
        $k = (int)$this->option('k', 5);
        $type = (string)$this->option('type', 'hybrid');

        $col = $manager->collection($collectionName);
        $results = $col->search($query, $k, ['type' => $type]);

        $this->info("==========================================================================================");
        $this->info("                 Vector Search Results ({$type}) - Collection: {$collectionName}");
        $this->info("                 Query: \"{$query}\"");
        $this->info("==========================================================================================");

        if (empty($results)) {
            $this->line("No matching documents found.");
            return 0;
        }

        $tableData = [];
        foreach ($results as $i => $item) {
            $contentSnippet = mb_strimwidth((string)$item['content'], 0, 80, '...');
            $tableData[] = [
                '#' . ($i + 1),
                $item['id'],
                round((float)$item['score'], 4),
                $contentSnippet,
            ];
        }

        $this->table(['Rank', 'Document ID', 'Score', 'Content Snippet'], $tableData);
        return 0;
    }
}
