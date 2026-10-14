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

class SearchSuggestCommand extends Command
{
    protected string $signature = 'search:suggest {index : Index name} {prefix : Keyword prefix} {--limit=5 : Maximum suggestions}';
    protected string $description = 'Generate real-time prefix auto-complete suggestions from search index';

    public function handle(): int
    {
        $indexName = (string)($this->argument(0) ?? 'products');
        $prefix = (string)($this->argument(1) ?? $this->option('prefix', ''));
        $limit = (int)($this->option('limit', 5));

        $this->info("========================================================================");
        $this->info("  NetFusion Framework — Real-Time Prefix Auto-Suggest [Prefix: '{$prefix}']");
        $this->info("========================================================================");

        /** @var SearchEngineManager|null $search */
        $search = $this->app->has('search.engine') ? $this->app->make('search.engine') : ($this->app->has('search') ? $this->app->make('search') : null);

        if ($search === null) {
            $this->warn("SearchEngineManager not registered in container.");
            return 1;
        }

        $suggestions = $search->suggest($indexName, $prefix, $limit);

        if (empty($suggestions)) {
            $this->line("  No suggestions found for prefix '{$prefix}'.");
        } else {
            foreach ($suggestions as $i => $sug) {
                $this->line(sprintf("  [%d] %-30s (Weight: %0.2f)", $i + 1, $sug->getText(), $sug->getScore()));
            }
        }

        $this->info("========================================================================");
        return 0;
    }
}
