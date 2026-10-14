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
use NetFusion\Core\Search\Models\SearchQuery;

class SearchQueryCommand extends Command
{
    protected string $signature = 'search:query {index : Index name} {query : Search query string} {--limit=10 : Maximum results} {--fuzzy : Enable typo tolerance}';
    protected string $description = 'Execute a search query against an index and display ranked matches';

    public function handle(): int
    {
        $indexName = (string)($this->argument(0) ?? 'products');
        $queryString = (string)($this->argument(1) ?? $this->option('query', ''));
        $limit = (int)($this->option('limit', 10));
        $fuzzy = (bool)($this->hasOption('fuzzy'));

        $this->info("========================================================================");
        $this->info("  NetFusion Framework — Search Query [Index: {$indexName}, Query: '{$queryString}']");
        $this->info("========================================================================");

        /** @var SearchEngineManager|null $search */
        $search = $this->app->has('search.engine') ? $this->app->make('search.engine') : ($this->app->has('search') ? $this->app->make('search') : null);

        if ($search === null) {
            $this->warn("SearchEngineManager not registered in container.");
            return 1;
        }

        if (!$search->hasIndex($indexName)) {
            $this->warn("Index [{$indexName}] not found.");
            return 1;
        }

        $query = (new SearchQuery($queryString))
            ->limit($limit)
            ->withFuzzy($fuzzy);

        $result = $search->search($indexName, $query);

        $this->line(sprintf("  • Matching Documents : %d (in %0.3f ms)", $result->getTotal(), $result->getExecutionTimeMs()));
        $this->line(sprintf("  • Max Relevance Score: %0.4f\n", $result->getMaxScore()));

        if (!empty($result->getSuggestions())) {
            $sugTexts = array_map(fn($s) => $s->getText(), $result->getSuggestions());
            $this->line("  \033[33mDid you mean:\033[0m " . implode(', ', $sugTexts) . "\n");
        }

        foreach ($result->getHits() as $i => $hit) {
            $doc = $hit['document'];
            $rank = $i + 1;
            $title = $doc->get('title', $doc->getId());
            $score = $hit['score'];
            $this->line(sprintf("  [%d] (Score: %0.4f) ID: %s | Title: %s", $rank, $score, $doc->getId(), $title));
            if (!empty($hit['highlight'])) {
                $this->line("      Snippet: " . strip_tags($hit['highlight']));
            }
        }

        $this->info("\n========================================================================");
        return 0;
    }
}
