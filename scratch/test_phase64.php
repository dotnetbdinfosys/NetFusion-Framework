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

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\Search\Contracts\SearchEngineInterface;
use NetFusion\Core\Search\Contracts\IndexInterface;
use NetFusion\Core\Search\Contracts\TokenizerInterface;
use NetFusion\Core\Search\Contracts\StemmerInterface;
use NetFusion\Core\Search\Contracts\RerankerInterface;
use NetFusion\Core\Search\Contracts\SuggestEngineInterface;
use NetFusion\Core\Search\Models\Document;
use NetFusion\Core\Search\Models\SearchQuery;
use NetFusion\Core\Search\Models\SearchResult;
use NetFusion\Core\Search\Models\FacetResult;
use NetFusion\Core\Search\Models\Suggestion;
use NetFusion\Core\Search\Analysis\StandardTokenizer;
use NetFusion\Core\Search\Analysis\NGramTokenizer;
use NetFusion\Core\Search\Analysis\PorterStemmer;
use NetFusion\Core\Search\Analysis\StopwordFilter;
use NetFusion\Core\Search\Analysis\SynonymFilter;
use NetFusion\Core\Search\Indexing\PositionalPosting;
use NetFusion\Core\Search\Indexing\InvertedIndex;
use NetFusion\Core\Search\Scoring\BM25Scorer;
use NetFusion\Core\Search\Scoring\PhraseScorer;
use NetFusion\Core\Search\Scoring\HybridReranker;
use NetFusion\Core\Search\Faceting\FacetEngine;
use NetFusion\Core\Search\Suggest\TrieSuggestEngine;
use NetFusion\Core\Search\Suggest\FuzzySpellCorrector;
use NetFusion\Core\Search\Analytics\SearchAnalyticsTracker;
use NetFusion\Core\Search\SearchEngineManager;

$testsPassed = 0;
$testsFailed = 0;

function assertTest(string $description, bool $condition): void
{
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  [PASS] {$description}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$description}\n";
        $testsFailed++;
    }
}

echo "========================================================================\n";
echo "  NetFusion Framework — Phase 64 Test Suite\n";
echo "  Enterprise Search & Discovery Engine, Vector+BM25 Hybrid Reranker,\n";
echo "  Faceted Taxonomy & Real-Time Auto-Suggest Engine\n";
echo "========================================================================\n\n";

// Setup Test SQLite Database
$testDbFile = __DIR__ . '/test_phase64.sqlite';
if (file_exists($testDbFile)) {
    @unlink($testDbFile);
}
$conn = new Connection([
    'driver' => 'sqlite',
    'database' => $testDbFile,
]);

// Run Search Migration
$migration = require __DIR__ . '/../database/migrations/2026_09_21_000044_create_search_engine_tables.php';
$migration->up($conn);

// Initialize Application Container
$app = new Application(realpath(__DIR__ . '/..'));
$app->instance('db', $conn);
$app->instance(Connection::class, $conn);

// -----------------------------------------------------------------------------
// 1. Text Analysis Components (Tokenizers, Stemmer, Stopwords, Synonyms)
// -----------------------------------------------------------------------------
echo "1. Testing Text Analysis Pipeline (Tokenizers, Stemmer, Filters)...\n";

// StandardTokenizer
$stdTokenizer = new StandardTokenizer();
$tokens = $stdTokenizer->tokenize("The Quick Brown FOX jumps over 123 lazy dogs!");
assertTest('StandardTokenizer produces lowercase tokens', in_array('quick', $tokens, true) && in_array('fox', $tokens, true) && in_array('123', $tokens, true));

$tokensWithPos = $stdTokenizer->tokenizeWithPositions("High-speed full-text search engine");
assertTest('StandardTokenizer extracts exact token positions and offsets', count($tokensWithPos) === 6 && $tokensWithPos[0]['position'] === 0 && $tokensWithPos[0]['token'] === 'high');

// NGramTokenizer
$ngramTokenizer = new NGramTokenizer(minGram: 2, maxGram: 3);
$ngrams = $ngramTokenizer->tokenize("Sony");
assertTest('NGramTokenizer generates character bigrams and trigrams', in_array('so', $ngrams, true) && in_array('on', $ngrams, true) && in_array('son', $ngrams, true));

// PorterStemmer
$stemmer = new PorterStemmer();
assertTest('PorterStemmer reduces words to roots', $stemmer->stem('connecting') === 'connect' && $stemmer->stem('playfulness') === 'play' && $stemmer->stem('deliveries') === 'deliveri');

// StopwordFilter
$stopFilter = new StopwordFilter();
$filtered = $stopFilter->filter(['the', 'search', 'is', 'very', 'fast']);
assertTest('StopwordFilter removes English stopwords', $filtered === ['search', 'fast']);

// SynonymFilter
$synFilter = new SynonymFilter([
    'laptop' => ['notebook', 'macbook', 'pc'],
]);
$expanded = $synFilter->expand(['apple', 'laptop']);
assertTest('SynonymFilter expands query tokens with synonyms', in_array('notebook', $expanded, true) && in_array('macbook', $expanded, true));

// -----------------------------------------------------------------------------
// 2. Inverted Indexing & Okapi BM25 Scoring
// -----------------------------------------------------------------------------
echo "\n2. Testing Inverted Indexing & Okapi BM25 Scoring...\n";

$index = new InvertedIndex('articles', [
    'bm25_k1' => 1.2,
    'bm25_b' => 0.75,
]);

$doc1 = Document::create('doc_1', [
    'title' => 'Introduction to Vector Search',
    'body' => 'Vector search and neural search allow semantic retrieval over embeddings.',
    'category' => 'AI',
    'price' => 50.0,
], vector: [0.9, 0.1, 0.0], boost: 1.0);

$doc2 = Document::create('doc_2', [
    'title' => 'Relational Databases and SQL',
    'body' => 'Relational database systems use structured query language for relational data.',
    'category' => 'Database',
    'price' => 30.0,
], vector: [0.0, 0.8, 0.2], boost: 1.0);

$doc3 = Document::create('doc_3', [
    'title' => 'Hybrid Search with BM25 and Vector Embeddings',
    'body' => 'Hybrid search combines BM25 keyword matching with vector neural search for optimal relevance.',
    'category' => 'AI',
    'price' => 120.0,
], vector: [0.8, 0.5, 0.1], boost: 1.5);

$index->addDocuments([$doc1, $doc2, $doc3]);

assertTest('InvertedIndex records document count', $index->count() === 3);
assertTest('InvertedIndex computes average document length', $index->getAverageDocumentLength() > 0.0);
assertTest('InvertedIndex records document frequency for indexed terms', $index->getDocumentFrequency('search') === 2);

// BM25Scorer unit test
$bm25 = new BM25Scorer(k1: 1.2, b: 0.75);
$scoreA = $bm25->score(tf: 2, df: 1, n: 10, docLength: 20, avgDocLength: 25.0);
$scoreB = $bm25->score(tf: 1, df: 5, n: 10, docLength: 50, avgDocLength: 25.0);
assertTest('BM25Scorer awards higher relevance to rare terms and higher TF', $scoreA > $scoreB);

// PhraseScorer unit test
$phraseScorer = new PhraseScorer();
$termPostings = [
    0 => ['doc_3' => new PositionalPosting('doc_3', 'body', 1, [0])],
    1 => ['doc_3' => new PositionalPosting('doc_3', 'body', 1, [1])],
];
$phraseBonus = $phraseScorer->scorePhrase($termPostings, 'doc_3', slop: 0);
assertTest('PhraseScorer scores exact sequential phrases', $phraseBonus > 0.0);

// -----------------------------------------------------------------------------
// 3. Hybrid Neural + Lexical Reranker (RRF & Dense Vector Blend)
// -----------------------------------------------------------------------------
echo "\n3. Testing Hybrid Neural + Lexical Reranking & RRF...\n";

// Cosine similarity
$vecA = [1.0, 0.0, 0.0];
$vecB = [1.0, 0.0, 0.0];
$vecC = [0.0, 1.0, 0.0];
assertTest('HybridReranker::cosineSimilarity computes identical vectors as 1.0', abs(HybridReranker::cosineSimilarity($vecA, $vecB) - 1.0) < 0.001);
assertTest('HybridReranker::cosineSimilarity computes orthogonal vectors as 0.0', abs(HybridReranker::cosineSimilarity($vecA, $vecC) - 0.0) < 0.001);

// Reciprocal Rank Fusion (RRF)
$reranker = new HybridReranker(rrfConstant: 60);
$rrfScores = $reranker->computeRRF([
    'bm25_system' => ['doc_3', 'doc_1', 'doc_2'],
    'vector_system' => ['doc_1', 'doc_3', 'doc_2'],
]);
assertTest('HybridReranker computes Reciprocal Rank Fusion (RRF) ordering', count($rrfScores) === 3 && array_key_first($rrfScores) === 'doc_3' || array_key_first($rrfScores) === 'doc_1');

// Hybrid Search Query execution
$hybridQuery = (SearchQuery::create('vector search'))
    ->withVector([0.85, 0.15, 0.0])
    ->withHybridWeight(0.5);

$hybridResult = $index->search($hybridQuery);
assertTest('InvertedIndex executes hybrid search returning ranked documents', $hybridResult->getTotal() >= 2);
assertTest('Hybrid search hits contain lexical and vector scores', isset($hybridResult->getHits()[0]['vector_score']));

// -------------------------------------------------------------
// 4. Faceted Taxonomy & Dynamic Aggregations
// -------------------------------------------------------------
echo "\n4. Testing Faceted Taxonomy & Numeric Range Aggregations...\n";

$facetEngine = new FacetEngine();
$facetEngine->configureRanges('price', [
    'under_50' => ['min' => 0.0, 'max' => 50.0],
    '50_to_100' => ['min' => 50.01, 'max' => 100.0],
    'over_100' => ['min' => 100.01, 'max' => 500.0],
]);

$computedFacets = $facetEngine->computeFacets([$doc1, $doc2, $doc3], ['category', 'price']);
assertTest('FacetEngine computes term frequency distribution for category', ($computedFacets['category']->getCount('AI') === 2));
assertTest('FacetEngine computes numeric range bucket counts for price', ($computedFacets['price']->getRanges()['under_50'] === 2) && ($computedFacets['price']->getRanges()['over_100'] === 1));

// -------------------------------------------------------------
// 5. Real-Time Prefix Auto-Suggest & Spell Correction
// -------------------------------------------------------------
echo "\n5. Testing Prefix Auto-Suggest (Trie) & Fuzzy Spell Corrector...\n";

$suggestEngine = new TrieSuggestEngine();
$suggestEngine->insert('PHP Framework', weight: 10.0);
$suggestEngine->insert('PHP Full-Stack Development', weight: 8.0);
$suggestEngine->insert('Python Machine Learning', weight: 5.0);

$suggestions = $suggestEngine->suggest('php', limit: 5);
assertTest('TrieSuggestEngine auto-completes prefix matches', count($suggestions) === 2);
assertTest('TrieSuggestEngine sorts suggestions by descending weight', $suggestions[0]->getText() === 'PHP Framework');
assertTest('TrieSuggestEngine generates highlighted prefix HTML', str_contains($suggestions[0]->getHighlighted() ?? '', '<strong>'));

// Fuzzy Spell Corrector
$spellCorrector = new FuzzySpellCorrector();
$spellCorrector->addWord('elasticsearch', 10);
$spellCorrector->addWord('framework', 20);
$spellCorrector->addWord('database', 15);

$correction = $spellCorrector->correctQuery('framewrk');
assertTest('FuzzySpellCorrector identifies and corrects misspelled query', $correction['has_correction'] && $correction['corrected_query'] === 'framework');

// -------------------------------------------------------------
// 6. Search Analytics & Telemetry Tracking
// -------------------------------------------------------------
echo "\n6. Testing Search Analytics & Latency Telemetry...\n";

$analytics = new SearchAnalyticsTracker($conn->getPdo());
$analytics->track('products', 'laptop', 5, 1.25);
$analytics->track('products', 'laptop', 5, 1.10);
$analytics->track('products', 'unfound_sku', 0, 0.85);

$metrics = $analytics->getMetrics();
assertTest('SearchAnalyticsTracker records total query count', $metrics['total_queries'] === 3);
assertTest('SearchAnalyticsTracker tracks zero-result queries', $metrics['zero_result_queries'] === 1);
assertTest('SearchAnalyticsTracker tracks top query frequencies', ($metrics['top_queries']['laptop'] ?? 0) === 2);

// -------------------------------------------------------------
// 7. Search Engine Manager Facade & Database Persistence
// -------------------------------------------------------------
echo "\n7. Testing Search Engine Manager Facade...\n";

$searchManager = new SearchEngineManager(
    config: [
        'default_index_options' => [
            'synonyms' => [
                'phone' => ['mobile', 'smartphone'],
            ]
        ]
    ],
    pdo: $conn->getPdo()
);

$prodIndex = $searchManager->createIndex('products');
assertTest('SearchEngineManager creates and registers index', $searchManager->hasIndex('products'));

$laptopDoc = Document::create('prod_101', [
    'title' => 'Dell XPS 15 Laptop',
    'description' => 'High performance developer ultrabook notebook with 32GB RAM.',
    'category' => 'Computers',
    'brand' => 'Dell',
    'price' => 1800.0,
], boost: 2.0);

$phoneDoc = Document::create('prod_102', [
    'title' => 'Google Pixel 9 Smartphone',
    'description' => 'Flagship mobile phone with advanced AI and camera capabilities.',
    'category' => 'Phones',
    'brand' => 'Google',
    'price' => 899.0,
]);

$searchManager->indexDocument('products', $laptopDoc);
$searchManager->indexDocument('products', $phoneDoc);

// Query with field boosting and filtering
$searchQuery = (SearchQuery::create('notebook'))
    ->boost('title', 3.0)
    ->filter('brand', 'Dell')
    ->addFacet('category');

$searchResult = $searchManager->search('products', $searchQuery);
assertTest('SearchEngineManager executes search matching synonym and filters', $searchResult->getTotal() === 1 && $searchResult->getDocuments()[0]->getId() === 'prod_101');
assertTest('SearchEngineManager generates highlighted snippets', !empty($searchResult->getHits()[0]['highlight']));

// Real-time prefix auto-suggest via manager
$mgrSuggestions = $searchManager->suggest('products', 'Dell', limit: 5);
assertTest('SearchEngineManager returns auto-suggest completions', !empty($mgrSuggestions));

// -------------------------------------------------------------
// 8. Framework Helpers & CLI Commands
// -------------------------------------------------------------
echo "\n8. Testing Framework Helpers & CLI Commands...\n";

// Register search in DI container
$app->instance('search.engine', $searchManager);
$app->instance('search', $searchManager);
$app->instance(SearchEngineManager::class, $searchManager);

assertTest('Global search() helper returns SearchEngineManager instance', search() instanceof SearchEngineManager);
assertTest('Global search("products") helper resolves IndexInterface', search('products') instanceof IndexInterface);

// search_query() helper
$helperResult = search_query('products', 'Pixel');
assertTest('search_query() helper executes query returning SearchResult', $helperResult instanceof SearchResult && $helperResult->getTotal() === 1);

// search_suggest() helper
$helperSuggests = search_suggest('products', 'Google');
assertTest('search_suggest() helper retrieves suggestions', !empty($helperSuggests));

// CLI Command: search:index
$kernel = new \NetFusion\Core\Console\Kernel($app);
ob_start();
$indexExit = $kernel->call('search:index', ['products']);
$indexOutput = ob_get_clean();
assertTest('SearchIndexCommand executes successfully (exit code 0)', $indexExit === 0);
assertTest('SearchIndexCommand outputs document count and statistics', str_contains($indexOutput, 'Enterprise Search Index') && str_contains($indexOutput, 'Document Count'));

// CLI Command: search:query
ob_start();
$queryExit = $kernel->call('search:query', ['products', 'Dell']);
$queryOutput = ob_get_clean();
assertTest('SearchQueryCommand executes successfully (exit code 0)', $queryExit === 0);
assertTest('SearchQueryCommand outputs search hits and relevance score', str_contains($queryOutput, 'Dell XPS 15 Laptop'));

// CLI Command: search:suggest
ob_start();
$suggestExit = $kernel->call('search:suggest', ['products', 'Dell']);
$suggestOutput = ob_get_clean();
assertTest('SearchSuggestCommand executes successfully (exit code 0)', $suggestExit === 0);
assertTest('SearchSuggestCommand outputs suggested keywords', str_contains($suggestOutput, 'Real-Time Prefix Auto-Suggest'));

// Clean up test sqlite
if (file_exists($testDbFile)) {
    @unlink($testDbFile);
}

echo "\n========================================================================\n";
echo "  Phase 64 Test Results: {$testsPassed} Passed, {$testsFailed} Failed\n";
echo "========================================================================\n\n";

exit($testsFailed > 0 ? 1 : 0);
