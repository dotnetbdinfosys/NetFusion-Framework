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

use NetFusion\Core\AI\AiManager;
use NetFusion\Core\Console\Command;
use Throwable;

class AiEmbedCommand extends Command
{
    protected string $name = 'ai:embed';
    protected string $description = 'Generate vector embeddings for input text and optionally store in vector database';

    public function handle(): int
    {
        $text = (string) $this->argument(0);
        if (empty($text)) {
            $this->error("Usage: php netfusion ai:embed \"Text to embed\" [--store] [--collection=default] [--id=doc_1]");
            return 1;
        }

        $driverName = $this->option('driver') ? (string) $this->option('driver') : null;
        $shouldStore = $this->option('store') !== null;
        $collection = $this->option('collection') ? (string) $this->option('collection') : 'default';
        $id = $this->option('id') ? (string) $this->option('id') : uniqid('doc_');

        /** @var AiManager $ai */
        $ai = $this->app->make(AiManager::class);

        $this->info("Generating vector embeddings [Driver: " . ($driverName ?? 'default') . "]...");

        try {
            $vector = $ai->embed($text, [], $driverName);

            $this->line("Embedding Vector Dimensions: " . count($vector));
            $this->line("Sample (first 5 dims): " . json_encode(array_slice($vector, 0, 5)));

            if ($shouldStore) {
                $store = $ai->vectorStore();
                $store->add($id, $vector, $text, ['stored_via_cli' => true], $collection);
                $this->info("Successfully indexed document [ID: {$id}] in collection '{$collection}' (Total in collection: " . $store->count($collection) . ")");
            }

            return 0;
        } catch (Throwable $e) {
            $this->error("Embedding Failed: " . $e->getMessage());
            return 1;
        }
    }
}
