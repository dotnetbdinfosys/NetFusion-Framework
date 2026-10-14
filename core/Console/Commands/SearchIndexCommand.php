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
use NetFusion\Core\Search\SearchEngineManager;

class SearchIndexCommand extends Command
{
    protected string $signature = 'search:index {index : Target index name} {--stats : Display index statistics}';
    protected string $description = 'Inspect, manage or display search index status and statistics';

    public function handle(): int
    {
        $indexName = (string)($this->argument(0) ?? $this->option('index', 'products'));

        $this->info("========================================================================");
        $this->info("  NetFusion Framework — Enterprise Search Index [{$indexName}]");
        $this->info("========================================================================");

        /** @var SearchEngineManager|null $search */
        $search = $this->app->has('search.engine') ? $this->app->make('search.engine') : ($this->app->has('search') ? $this->app->make('search') : null);

        if ($search === null) {
            $this->warn("SearchEngineManager not registered in container.");
            return 1;
        }

        if (!$search->hasIndex($indexName)) {
            $search->createIndex($indexName);
            $this->info("  ✔ Index [{$indexName}] initialized.");
        }

        $index = $search->getIndex($indexName);
        $this->line(sprintf("  • Index Name         : %s", $index->getName()));
        $this->line(sprintf("  • Document Count     : %d", $index->count()));

        if (method_exists($index, 'getAverageDocumentLength')) {
            $this->line(sprintf("  • Avg Doc Length     : %0.2f tokens", $index->getAverageDocumentLength()));
        }

        $analytics = $search->getAnalytics()->getMetrics();
        $this->line(sprintf("  • Total Query Volume : %d", $analytics['total_queries']));
        $this->line(sprintf("  • Avg Query Latency  : %0.3f ms", $analytics['avg_latency_ms']));

        $this->info("========================================================================");
        return 0;
    }
}
