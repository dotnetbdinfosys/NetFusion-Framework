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

namespace NetFusion\Core\Search;

use NetFusion\Core\Search\Contracts\SearchEngineInterface;
use NetFusion\Core\Search\Contracts\IndexInterface;
use NetFusion\Core\Search\Indexing\InvertedIndex;
use NetFusion\Core\Search\Models\Document;
use NetFusion\Core\Search\Models\SearchQuery;
use NetFusion\Core\Search\Models\SearchResult;
use NetFusion\Core\Search\Suggest\TrieSuggestEngine;
use NetFusion\Core\Search\Suggest\FuzzySpellCorrector;
use NetFusion\Core\Search\Analytics\SearchAnalyticsTracker;
use NetFusion\Core\Search\Exceptions\IndexNotFoundException;

class SearchEngineManager implements SearchEngineInterface
{
    /**
     * @var array<string, IndexInterface> IndexName => IndexInstance
     */
    protected array $indices = [];

    /**
     * @var array<string, TrieSuggestEngine> IndexName => SuggestEngine
     */
    protected array $suggestEngines = [];

    /**
     * @var array<string, FuzzySpellCorrector> IndexName => SpellCorrector
     */
    protected array $spellCorrectors = [];

    protected SearchAnalyticsTracker $analytics;

    /**
     * @param array<string, mixed> $config
     * @param \PDO|null $pdo
     */
    public function __construct(
        protected array $config = [],
        protected ?\PDO $pdo = null
    ) {
        $this->analytics = new SearchAnalyticsTracker($this->pdo);
    }

    public function getAnalytics(): SearchAnalyticsTracker
    {
        return $this->analytics;
    }

    public function createIndex(string $indexName, array $options = []): IndexInterface
    {
        $mergedOptions = array_merge($this->config['default_index_options'] ?? [], $options);
        $index = new InvertedIndex($indexName, $mergedOptions);

        $this->indices[$indexName] = $index;
        $this->suggestEngines[$indexName] = new TrieSuggestEngine();
        $this->spellCorrectors[$indexName] = new FuzzySpellCorrector();

        if ($this->pdo !== null) {
            $this->persistIndexMetadata($indexName, $mergedOptions);
        }

        return $index;
    }

    public function getIndex(string $indexName): IndexInterface
    {
        if (!isset($this->indices[$indexName])) {
            throw new IndexNotFoundException($indexName);
        }
        return $this->indices[$indexName];
    }

    public function hasIndex(string $indexName): bool
    {
        return isset($this->indices[$indexName]);
    }

    public function deleteIndex(string $indexName): bool
    {
        if (!isset($this->indices[$indexName])) {
            return false;
        }

        unset($this->indices[$indexName]);
        unset($this->suggestEngines[$indexName]);
        unset($this->spellCorrectors[$indexName]);

        if ($this->pdo !== null) {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM nf_search_indices WHERE name = :name");
                $stmt->execute([':name' => $indexName]);
            } catch (\Throwable) {
            }
        }

        return true;
    }

    public function indexDocument(string $indexName, Document $doc): static
    {
        $index = $this->getIndex($indexName);
        $index->addDocument($doc);

        // Train Trie Suggest & Spell Corrector on title/body terms
        $suggestEngine = $this->suggestEngines[$indexName] ?? null;
        $spellCorrector = $this->spellCorrectors[$indexName] ?? null;

        if ($suggestEngine !== null || $spellCorrector !== null) {
            $combined = $doc->getCombinedText(['id', 'vector']);
            if ($spellCorrector !== null) {
                $spellCorrector->trainFromText($combined);
            }
            if ($suggestEngine !== null) {
                // Add title phrases or field values
                foreach ($doc->getFields() as $field => $val) {
                    if (is_string($val) && strlen($val) <= 100) {
                        $suggestEngine->insert($val, $doc->getBoost(), ['doc_id' => $doc->getId()]);
                    }
                }
            }
        }

        if ($this->pdo !== null) {
            $this->persistDocumentRecord($indexName, $doc);
        }

        return $this;
    }

    public function search(string $indexName, SearchQuery $query): SearchResult
    {
        $index = $this->getIndex($indexName);
        $result = $index->search($query);

        // Track search analytics
        $this->analytics->track(
            indexName: $indexName,
            query: $query->getQueryString(),
            hitsCount: $result->getTotal(),
            latencyMs: $result->getExecutionTimeMs()
        );

        // Generate Spell suggestions if zero results
        if ($result->getTotal() === 0 && !empty($query->getQueryString())) {
            $spell = $this->spellCorrectors[$indexName] ?? null;
            if ($spell !== null) {
                $correction = $spell->correctQuery($query->getQueryString());
                if ($correction['has_correction']) {
                    $suggestions = array_map(fn($s) => new \NetFusion\Core\Search\Models\Suggestion($s, 0.9), $correction['suggestions']);
                    return new SearchResult(
                        hits: [],
                        total: 0,
                        executionTimeMs: $result->getExecutionTimeMs(),
                        facets: $result->getFacets(),
                        suggestions: $suggestions,
                        maxScore: 0.0
                    );
                }
            }
        }

        return $result;
    }

    public function suggest(string $indexName, string $prefix, int $limit = 5): array
    {
        $suggestEngine = $this->suggestEngines[$indexName] ?? null;
        if ($suggestEngine === null) {
            return [];
        }
        return $suggestEngine->suggest($prefix, $limit);
    }

    public function getIndices(): array
    {
        return $this->indices;
    }

    protected function persistIndexMetadata(string $indexName, array $options): void
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO nf_search_indices (name, options, created_at)
                VALUES (:name, :options, :created_at)
                ON CONFLICT(name) DO UPDATE SET options = :options");
            $stmt->execute([
                ':name' => $indexName,
                ':options' => json_encode($options),
                ':created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }

    protected function persistDocumentRecord(string $indexName, Document $doc): void
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO nf_search_documents (index_name, doc_id, fields, vector, boost, created_at)
                VALUES (:index_name, :doc_id, :fields, :vector, :boost, :created_at)
                ON CONFLICT(index_name, doc_id) DO UPDATE SET fields = :fields, vector = :vector, boost = :boost");
            $stmt->execute([
                ':index_name' => $indexName,
                ':doc_id' => $doc->getId(),
                ':fields' => json_encode($doc->getFields()),
                ':vector' => $doc->getVector() !== null ? json_encode($doc->getVector()) : null,
                ':boost' => $doc->getBoost(),
                ':created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }
}
