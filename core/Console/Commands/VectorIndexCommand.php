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

class VectorIndexCommand extends Command
{
    protected string $name = 'vector:index';
    protected string $signature = 'vector:index {collection : Collection name} {--id= : Document ID} {--text= : Raw text to embed and index} {--file= : File path to ingest and chunk}';
    protected string $description = 'Ingest and index text or files into a vector collection';

    public function handle(): int
    {
        /** @var VectorEngineManager $manager */
        $manager = function_exists('vector') ? vector() : (function_exists('app') && app()->has('vector') ? app('vector') : new VectorEngineManager());

        $collectionName = (string)($this->argument(0) ?? 'default');
        $col = $manager->collection($collectionName);

        $file = $this->option('file');
        $text = $this->option('text');
        $id = $this->option('id') ?? uniqid('doc_');

        if (!empty($file)) {
            $filePath = (string)$file;
            if (!file_exists($filePath)) {
                $this->error("File not found: {$filePath}");
                return 1;
            }

            $content = (string)file_get_contents($filePath);
            $chunks = $col->ingest((string)$id, $content, ['source' => $filePath]);
            $this->info("Ingested and indexed " . count($chunks) . " chunks into collection '{$collectionName}'.");
            return 0;
        }

        if (!empty($text)) {
            $col->addText((string)$id, (string)$text);
            $this->info("Indexed document '{$id}' into collection '{$collectionName}'.");
            return 0;
        }

        $this->error("Please provide either --text or --file to index.");
        return 1;
    }
}
