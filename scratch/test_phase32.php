<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework - Phase 32 Test Suite
|--------------------------------------------------------------------------
|
| Tests for Enterprise Multi-Channel Search & Full-Text Indexing Engine.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Database\Model;
use NetFusion\Core\Search\Contracts\SearchDriverInterface;
use NetFusion\Core\Search\Drivers\MemoryDriver;
use NetFusion\Core\Search\Drivers\MysqlFulltextDriver;
use NetFusion\Core\Search\Drivers\SqliteFtsDriver;
use NetFusion\Core\Search\SearchBuilder;
use NetFusion\Core\Search\SearchManager;
use NetFusion\Core\Search\SearchResult;
use NetFusion\Core\Search\Searchable;

$basePath = realpath(__DIR__ . '/..');
$app = new Application($basePath);

$passed = 0;
$failed = 0;

function assert_test(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  \033[32m✔ PASS\033[0m: {$message}\n";
    } else {
        $failed++;
        echo "  \033[31m✖ FAIL\033[0m: {$message}\n";
    }
}

echo "\n=======================================================\n";
echo " NetFusion Phase 32: Full-Text Search Engine Test Suite \n";
echo "=======================================================\n\n";

// -----------------------------------------------------------------------------
// 1. MemoryDriver Tests (BM25, Inverted Index, Tokenizer, Filtering)
// -----------------------------------------------------------------------------
echo "[1] Testing MemoryDriver (In-Memory Inverted Index & BM25 Scoring)...\n";

$memDriver = new MemoryDriver();

$memDriver->index('articles', [
    [
        'id'       => 1,
        'title'    => 'Introduction to NetFusion Framework',
        'content'  => 'NetFusion is a lightning fast zero dependency PHP enterprise framework.',
        'category' => 'php',
        'status'   => 'published',
    ],
    [
        'id'       => 2,
        'title'    => 'Advanced Database Optimization in MySQL',
        'content'  => 'Optimize indexes, fulltext queries, and query builder execution time.',
        'category' => 'database',
        'status'   => 'published',
    ],
    [
        'id'       => 3,
        'title'    => 'Building Microservices with PHP 8.2 and NetFusion',
        'content'  => 'Scalable microservices architecture using NetFusion event broadcasting and queue.',
        'category' => 'php',
        'status'   => 'draft',
    ],
    [
        'id'       => 4,
        'title'    => 'Full-Text Search Engine Architectures',
        'content'  => 'Comparing BM25, TF-IDF, SQLite FTS5, and Lucene search algorithms.',
        'category' => 'search',
        'status'   => 'published',
    ],
]);

assert_test($memDriver->count('articles') === 4, 'MemoryDriver correctly indexed 4 documents');
assert_test($memDriver->hasIndex('articles') === true, 'MemoryDriver confirms index existence');

// Search for "NetFusion"
$res = $memDriver->search('articles', 'NetFusion');
assert_test($res['total'] === 2, 'Search for "NetFusion" matched 2 articles');
assert_test(count($res['hits']) === 2, 'Hits count matches total');

// Title boost verification: Doc 1 has "NetFusion" in title and content, Doc 3 has it in title and content.
$topHit = $res['hits'][0];
assert_test($topHit['score'] > 0, 'Match has positive BM25 score');

// Prefix / wildcard search
$prefixRes = $memDriver->search('articles', 'microserv*');
assert_test($prefixRes['total'] === 1 && $prefixRes['hits'][0]['id'] === 3, 'Wildcard search "microserv*" matched Doc 3');

// Filtering test
$filterRes = $memDriver->search('articles', 'NetFusion', [
    'filters' => ['status' => 'published'],
]);
assert_test($filterRes['total'] === 1 && $filterRes['hits'][0]['id'] === 1, 'Filter by status="published" isolated Doc 1');

// Pagination test
$pageRes = $memDriver->search('articles', 'NetFusion', ['limit' => 1, 'offset' => 0]);
assert_test(count($pageRes['hits']) === 1 && $pageRes['total'] === 2, 'Pagination limit=1 returns 1 hit while total remains 2');

// Delete & Flush
$memDriver->delete('articles', 2);
assert_test($memDriver->count('articles') === 3, 'Document 2 deleted successfully');
$memDriver->flush('articles');
assert_test($memDriver->count('articles') === 0, 'Index flushed successfully');

// -----------------------------------------------------------------------------
// 2. SqliteFtsDriver Tests (FTS5 / FTS4 / Fallback)
// -----------------------------------------------------------------------------
echo "\n[2] Testing SqliteFtsDriver (SQLite FTS5 / BM25)...\n";

$sqliteDriver = new SqliteFtsDriver(':memory:');

$sqliteDriver->index('products', [
    [
        'id'    => 'p-101',
        'title' => 'Apple MacBook Pro M3 Max',
        'brand' => 'Apple',
        'desc'  => 'Supercharged by M3 Max with blazing fast memory bandwidth.',
        'stock' => 15,
    ],
    [
        'id'    => 'p-102',
        'title' => 'Dell XPS 15 Laptop',
        'brand' => 'Dell',
        'desc'  => 'Intel Core i9 high performance laptop with OLED display.',
        'stock' => 8,
    ],
    [
        'id'    => 'p-103',
        'title' => 'Apple iPad Pro 13-inch',
        'brand' => 'Apple',
        'desc'  => 'Ultra retina XDR display with M4 processor and Apple Pencil Pro.',
        'stock' => 25,
    ],
]);

assert_test($sqliteDriver->count('products') === 3, 'SqliteFtsDriver indexed 3 products');

// Search "Apple"
$sqlRes = $sqliteDriver->search('products', 'Apple');
assert_test($sqlRes['total'] === 2, 'Sqlite search for "Apple" matched 2 items');
assert_test(in_array('p-101', array_column($sqlRes['hits'], 'id'), true), 'Hit contains p-101');
assert_test(in_array('p-103', array_column($sqlRes['hits'], 'id'), true), 'Hit contains p-103');

// Search with filter
$sqlFilterRes = $sqliteDriver->search('products', 'Apple', [
    'filters' => ['brand' => 'Apple', 'stock' => 25],
]);
assert_test(count($sqlFilterRes['hits']) === 1 && $sqlFilterRes['hits'][0]['id'] === 'p-103', 'Sqlite filtering works accurately');

// Delete & Drop
$sqliteDriver->delete('products', 'p-102');
assert_test($sqliteDriver->count('products') === 2, 'Product p-102 deleted from SQLite FTS');
$sqliteDriver->dropIndex('products');
assert_test($sqliteDriver->hasIndex('products') === false, 'Index dropped successfully from SQLite');

// -----------------------------------------------------------------------------
// 3. MysqlFulltextDriver Tests (Query Formatting & Contract)
// -----------------------------------------------------------------------------
echo "\n[3] Testing MysqlFulltextDriver...\n";

// Use SQLite PDO to test driver contract & SQL fallback mechanism safely
$dummyPdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$mysqlDriver = new MysqlFulltextDriver($dummyPdo, 'test_mysql_search_index');
assert_test($mysqlDriver instanceof SearchDriverInterface, 'MysqlFulltextDriver implements SearchDriverInterface');

$mysqlDriver->index('docs', [
    ['id' => 'd1', 'title' => 'NetFusion Enterprise Guide', 'content' => 'Complete documentation for high throughput PHP.'],
    ['id' => 'd2', 'title' => 'PHP 8.2 Attributes', 'content' => 'Deep dive into PHP 8.2 native attributes.'],
]);

assert_test($mysqlDriver->count('docs') === 2, 'MysqlFulltextDriver indexed 2 documents');
$myRes = $mysqlDriver->search('docs', 'NetFusion');
assert_test($myRes['total'] >= 1 && $myRes['hits'][0]['id'] === 'd1', 'MysqlFulltextDriver search matched document d1');

// -----------------------------------------------------------------------------
// 4. SearchResult Value Object Tests
// -----------------------------------------------------------------------------
echo "\n[4] Testing SearchResult Value Object...\n";

$dummyHits = [
    ['id' => 10, 'score' => 2.5, 'document' => ['id' => 10, 'title' => 'First']],
    ['id' => 20, 'score' => 1.8, 'document' => ['id' => 20, 'title' => 'Second']],
];
$sr = new SearchResult($dummyHits, 15, 1.25, 'test query', 2, 2);

assert_test($sr->count() === 2, 'SearchResult count() returns current hits count');
assert_test($sr->total() === 15, 'SearchResult total() returns 15');
assert_test($sr->page() === 2, 'SearchResult page() returns 2');
assert_test($sr->lastPage() === 8, 'SearchResult lastPage() calculates ceil(15/2) = 8');
assert_test($sr->hasMorePages() === true, 'SearchResult hasMorePages() is true');
assert_test($sr->ids() === [10, 20], 'SearchResult ids() extracts IDs array [10, 20]');
assert_test($sr->took() === 1.25, 'SearchResult took() returns 1.25ms');
assert_test($sr->isNotEmpty() === true, 'SearchResult isNotEmpty() is true');

$json = json_encode($sr);
assert_test(str_contains((string) $json, '"total":15'), 'SearchResult serializes to JSON properly');

// -----------------------------------------------------------------------------
// 5. SearchManager Multi-Driver Orchestrator Tests
// -----------------------------------------------------------------------------
echo "\n[5] Testing SearchManager...\n";

$app = Application::getInstance();
$manager = new SearchManager($app);

assert_test($manager->getDefaultDriver() === 'memory', 'Default search driver is memory');
assert_test($manager->driver('memory') instanceof MemoryDriver, 'driver("memory") returns MemoryDriver');

// Custom driver extension
$manager->extend('mock', function () {
    return new class implements SearchDriverInterface {
        public function search(string $index, string $query, array $options = []): array {
            return ['hits' => [['id' => 999, 'score' => 9.9, 'document' => ['id' => 999]]], 'total' => 1, 'took' => 0.1];
        }
        public function index(string $index, array $documents): void {}
        public function delete(string $index, array|int|string $ids): void {}
        public function flush(string $index): void {}
        public function createIndex(string $index, array $fields = []): void {}
        public function dropIndex(string $index): void {}
        public function hasIndex(string $index): bool { return true; }
        public function count(string $index): int { return 1; }
    };
});

assert_test($manager->driver('mock')->count('any') === 1, 'Custom extended driver resolved and executed');
$manager->setDefaultDriver('mock');
$mockResult = $manager->search('mock_index', 'hello');
assert_test($mockResult instanceof SearchResult, 'SearchManager::search returns SearchResult instance');
assert_test($mockResult->ids() === [999], 'Mock search result returned ID 999');

// Reset default
$manager->setDefaultDriver('memory');

// -----------------------------------------------------------------------------
// 6. Searchable Trait & SearchBuilder Tests
// -----------------------------------------------------------------------------
echo "\n[6] Testing Searchable Trait and SearchBuilder...\n";

class TestSearchArticle extends Model
{
    use Searchable;

    protected string $table = 'test_search_articles';
    protected array $fillable = ['id', 'title', 'content', 'author', 'status'];
}

$art1 = new TestSearchArticle([
    'id'      => 101,
    'title'   => 'Mastering Modern PHP with NetFusion',
    'content' => 'Techniques for building robust, scalable web architectures.',
    'author'  => 'Ashish',
    'status'  => 'published',
]);

assert_test($art1->searchableAs() === 'test_search_articles', 'searchableAs() returns table name');
assert_test($art1->searchableId() === 101, 'searchableId() returns model primary key');

$searchableData = $art1->toSearchableArray();
assert_test($searchableData['id'] === 101 && $searchableData['author'] === 'Ashish', 'toSearchableArray() formats model correctly');

// Test indexing via Model
$art1->searchIndex($manager);
assert_test($manager->count('test_search_articles') === 1, 'Model::searchIndex indexed model into SearchManager');

// Model Search Builder
$builder = TestSearchArticle::search('Mastering Modern PHP', ['driver' => 'memory', 'search' => $manager]);
$rawRes = $builder->raw();
assert_test($rawRes->total() === 1, 'SearchBuilder::raw() found 1 match');
assert_test($rawRes->ids() === [101], 'SearchBuilder returned ID 101');

// Test remove from search
$art1->searchRemove($manager);
assert_test($manager->count('test_search_articles') === 0, 'Model::searchRemove deleted model from search index');

// -----------------------------------------------------------------------------
// 7. Global Helper & Application DI Tests
// -----------------------------------------------------------------------------
echo "\n[7] Testing search() Helper and Service Container Integration...\n";

assert_test($app->has('search'), 'Application container has "search" registered');
assert_test($app->make('search') instanceof SearchManager, '"search" resolves to SearchManager');
assert_test(function_exists('search'), 'Global search() helper function exists');
assert_test(search() instanceof SearchManager, 'search() returns SearchManager instance');

// Seed via helper
search()->index('kb', [
    ['id' => 'k1', 'title' => 'Authentication and Security', 'tags' => ['auth', 'jwt']],
    ['id' => 'k2', 'title' => 'Webhooks and Event Replay', 'tags' => ['webhook', 'events']],
]);

$kbResult = search('kb', 'Authentication');
assert_test($kbResult instanceof SearchResult, 'search($index, $query) returns SearchResult');
assert_test($kbResult->total() === 1 && $kbResult->ids() === ['k1'], 'search() helper query matched k1');

// -----------------------------------------------------------------------------
// 8. CLI Commands Verification
// -----------------------------------------------------------------------------
echo "\n[8] Testing CLI Search Commands...\n";

$indexCmd = new \NetFusion\Core\Console\Commands\SearchIndexCommand($app);
assert_test($indexCmd->getName() === 'search:index', 'SearchIndexCommand name is search:index');

$flushCmd = new \NetFusion\Core\Console\Commands\SearchFlushCommand($app);
assert_test($flushCmd->getName() === 'search:flush', 'SearchFlushCommand name is search:flush');

$queryCmd = new \NetFusion\Core\Console\Commands\SearchQueryCommand($app);
assert_test($queryCmd->getName() === 'search:query', 'SearchQueryCommand name is search:query');

// Execute CLI search:query command on 'kb'
ob_start();
$queryCmd->setInput(['kb', 'Authentication'], ['limit' => 5]);
$exitCode = $queryCmd->handle();
$cmdOutput = ob_get_clean();
assert_test($exitCode === 0, 'SearchQueryCommand exited with 0');
assert_test(str_contains($cmdOutput, 'Total Matches: 1'), 'SearchQueryCommand output contains match statistics');

// -----------------------------------------------------------------------------
// Final Summary
// -----------------------------------------------------------------------------
echo "\n=======================================================\n";
echo " Phase 32 Test Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n\n";

exit($failed > 0 ? 1 : 0);
