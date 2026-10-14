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
use NetFusion\Core\Graph\Contracts\GraphStoreInterface;
use NetFusion\Core\Graph\Contracts\NodeInterface;
use NetFusion\Core\Graph\Contracts\EdgeInterface;
use NetFusion\Core\Graph\Contracts\QueryEngineInterface;
use NetFusion\Core\Graph\Contracts\TraverserInterface;
use NetFusion\Core\Graph\Contracts\ReasonerInterface;
use NetFusion\Core\Graph\Models\Node;
use NetFusion\Core\Graph\Models\Edge;
use NetFusion\Core\Graph\Models\Path;
use NetFusion\Core\Graph\Models\QueryResult;
use NetFusion\Core\Graph\Models\Triple;
use NetFusion\Core\Graph\Storage\MemoryGraphStore;
use NetFusion\Core\Graph\Storage\DatabaseGraphStore;
use NetFusion\Core\Graph\Cypher\CypherToken;
use NetFusion\Core\Graph\Cypher\CypherLexer;
use NetFusion\Core\Graph\Cypher\CypherParser;
use NetFusion\Core\Graph\Cypher\PatternMatcher;
use NetFusion\Core\Graph\Cypher\CypherEngine;
use NetFusion\Core\Graph\Algorithms\GraphTraverser;
use NetFusion\Core\Graph\Algorithms\PageRank;
use NetFusion\Core\Graph\Algorithms\CommunityDetection;
use NetFusion\Core\Graph\Algorithms\ConnectedComponents;
use NetFusion\Core\Graph\Reasoner\RDFTripleStore;
use NetFusion\Core\Graph\Reasoner\InferenceRule;
use NetFusion\Core\Graph\Reasoner\InferenceEngine;
use NetFusion\Core\Graph\GraphManager;

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
echo "  NetFusion Framework — Phase 65 Test Suite\n";
echo "  Enterprise Multi-Model Graph Database Engine, Cypher Query Parser,\n";
echo "  Graph Traverser & Knowledge Graph Reasoner Engine\n";
echo "========================================================================\n\n";

// Set up temporary SQLite database for tests
$testDbFile = __DIR__ . '/test_phase65.sqlite';
if (file_exists($testDbFile)) {
    @unlink($testDbFile);
}

$conn = new Connection([
    'driver' => 'sqlite',
    'database' => $testDbFile,
]);
$pdo = $conn->getPdo();

// Run migration
$migration = require __DIR__ . '/../database/migrations/2026_09_21_000045_create_graph_database_tables.php';
$migration->up($conn);

// Initialize Application Container
$app = new Application(realpath(__DIR__ . '/..'));
$app->instance('db', $conn);
$app->instance(Connection::class, $conn);

// -----------------------------------------------------------------------------
// 1. Graph Models & Memory Graph Store
// -----------------------------------------------------------------------------
echo "1. Testing Graph Models & Memory Graph Store...\n";

$store = new MemoryGraphStore();

$alice = Node::create('u1', ['User', 'Person'], ['name' => 'Alice', 'age' => 30, 'city' => 'Dhaka']);
$bob = Node::create('u2', ['User', 'Person'], ['name' => 'Bob', 'age' => 25, 'city' => 'Chittagong']);
$charlie = Node::create('u3', ['User', 'Person'], ['name' => 'Charlie', 'age' => 35, 'city' => 'Dhaka']);
$company = Node::create('c1', ['Company'], ['name' => 'DotNetBD', 'sector' => 'Tech']);

$store->addNode($alice);
$store->addNode($bob);
$store->addNode($charlie);
$store->addNode($company);

assertTest('MemoryGraphStore records node count', $store->countNodes() === 4);
assertTest('MemoryGraphStore retrieves node by ID', $store->getNode('u1')?->getProperty('name') === 'Alice');
assertTest('MemoryGraphStore filters nodes by Label', count($store->getNodes('User')) === 3 && count($store->getNodes('Company')) === 1);

$e1 = Edge::create('e1', 'u1', 'u2', 'FRIEND_OF', directed: true, weight: 1.0, properties: ['since' => 2022]);
$e2 = Edge::create('e2', 'u2', 'u3', 'FRIEND_OF', directed: true, weight: 2.0, properties: ['since' => 2023]);
$e3 = Edge::create('e3', 'u1', 'c1', 'WORKS_AT', directed: true, weight: 1.5, properties: ['role' => 'Lead Engineer']);
$e4 = Edge::create('e4', 'u3', 'c1', 'WORKS_AT', directed: true, weight: 1.5, properties: ['role' => 'Architect']);

$store->addEdge($e1);
$store->addEdge($e2);
$store->addEdge($e3);
$store->addEdge($e4);

assertTest('MemoryGraphStore records edge count', $store->countEdges() === 4);
assertTest('MemoryGraphStore filters out-edges by type', count($store->getOutEdges('u1', 'FRIEND_OF')) === 1 && count($store->getOutEdges('u1', 'WORKS_AT')) === 1);
assertTest('MemoryGraphStore retrieves adjacent neighbors', count($store->getAdjacentNodes('u1', direction: 'out')) === 2);

// -----------------------------------------------------------------------------
// 2. Database Graph Store (Persistent SQLite)
// -----------------------------------------------------------------------------
echo "\n2. Testing Database Graph Store (Persistent SQLite)...\n";

$dbStore = new DatabaseGraphStore($pdo);
$dbStore->addNode($alice);
$dbStore->addNode($bob);
$dbStore->addNode($charlie);
$dbStore->addNode($company);
$dbStore->addEdge($e1);
$dbStore->addEdge($e2);
$dbStore->addEdge($e3);

assertTest('DatabaseGraphStore persists nodes in SQLite', $dbStore->countNodes() === 4);
assertTest('DatabaseGraphStore persists edges in SQLite', $dbStore->countEdges() === 3);
assertTest('DatabaseGraphStore hydrates node with labels and properties', $dbStore->getNode('u1')?->hasLabel('Person') && $dbStore->getNode('u1')?->getProperty('city') === 'Dhaka');
assertTest('DatabaseGraphStore retrieves adjacent neighbors from SQLite', count($dbStore->getAdjacentNodes('u1', direction: 'out')) === 2);

// -----------------------------------------------------------------------------
// 3. Cypher Lexer & Parser
// -----------------------------------------------------------------------------
echo "\n3. Testing Cypher Lexer & Parser...\n";

$lexer = new CypherLexer();
$tokens = $lexer->tokenize("MATCH (u:User {name: 'Alice'})-[:FRIEND_OF*1..2]->(f:User) WHERE f.age > 20 RETURN f.name AS friend_name ORDER BY f.age DESC LIMIT 5;");
assertTest('CypherLexer scans query into typed token stream', count($tokens) > 20 && $tokens[0]->isKeyword('MATCH'));

$parser = new CypherParser();
$ast = $parser->parse("MATCH (u:User)-[r:FRIEND_OF]->(f:User) WHERE u.age >= 25 AND f.city = 'Dhaka' RETURN u.name, f.name AS friend ORDER BY f.name ASC SKIP 0 LIMIT 10");
assertTest('CypherParser constructs AST with match path elements', count($ast->matchPatterns) === 1 && count($ast->matchPatterns[0]->elements) === 3);
assertTest('CypherParser parses WHERE binary & logical conditions', $ast->whereExpression !== null && $ast->whereExpression['type'] === 'logical');
assertTest('CypherParser parses RETURN projection items and aliases', count($ast->returnItems) === 2 && $ast->returnItems[1]->alias === 'friend');
assertTest('CypherParser parses ORDER BY and pagination limits', count($ast->orderByItems) === 1 && $ast->limit === 10);

// -----------------------------------------------------------------------------
// 4. Cypher Query Execution Engine (CRUD, Matching, Aggregations)
// -----------------------------------------------------------------------------
echo "\n4. Testing Cypher Query Execution Engine...\n";

$cypherEngine = new CypherEngine($store);

// MATCH query with WHERE filter and property projection
$q1 = $cypherEngine->query("MATCH (u:User)-[:FRIEND_OF]->(f:User) WHERE u.name = 'Alice' RETURN f.name, f.age");
assertTest('CypherEngine executes 1-hop relationship traversal', $q1->count() === 1 && $q1->first()['f.name'] === 'Bob');

// Multi-hop path traversal: Alice -> Bob -> Charlie
$q2 = $cypherEngine->query("MATCH (u:User {name: 'Alice'})-[:FRIEND_OF*1..2]->(target:User) RETURN target.name");
$targetNames = $q2->pluck('target.name');
assertTest('CypherEngine executes variable-length path traversal (*1..2)', in_array('Bob', $targetNames, true) && in_array('Charlie', $targetNames, true));

// Aggregations: COUNT, AVG, MIN, MAX
$q3 = $cypherEngine->query("MATCH (u:User) RETURN count(u) AS total_users, avg(u.age) AS avg_age, min(u.age) AS min_age, max(u.age) AS max_age");
$aggRow = $q3->first();
assertTest('CypherEngine computes aggregations (COUNT, AVG, MIN, MAX)', $aggRow['total_users'] === 3 && $aggRow['avg_age'] === 30.0 && $aggRow['min_age'] === 25.0 && $aggRow['max_age'] === 35.0);

// CREATE new node and relationship via Cypher
$qCreate = $cypherEngine->query("CREATE (d:User {id: 'u4', name: 'Diana', age: 28, city: 'Sylhet'})");
assertTest('CypherEngine creates new node via CREATE clause', $qCreate->getStats()['nodes_created'] === 1 && $store->hasNode('u4'));

$qCreateEdge = $cypherEngine->query("MATCH (a:User {name: 'Alice'}), (d:User {name: 'Diana'}) CREATE (a)-[:FRIEND_OF {since: 2024}]->(d)");
assertTest('CypherEngine creates relationship between matched nodes', $store->countEdges() === 5);

// SET clause: property update and label addition
$qSet = $cypherEngine->query("MATCH (u:User {name: 'Diana'}) SET u.city = 'Dhaka', u:VIP RETURN u.city");
assertTest('CypherEngine executes SET property update and label assignment', $store->getNode('u4')?->getProperty('city') === 'Dhaka' && $store->getNode('u4')?->hasLabel('VIP'));

// DELETE clause
$qDel = $cypherEngine->query("MATCH (u:User {name: 'Diana'}) DETACH DELETE u");
assertTest('CypherEngine executes DETACH DELETE removing node and connected edges', !$store->hasNode('u4') && $store->countEdges() === 4);

// -----------------------------------------------------------------------------
// 5. Graph Traversals & Pathfinding Algorithms
// -----------------------------------------------------------------------------
echo "\n5. Testing Graph Traversals & Pathfinding Algorithms...\n";

$traverser = new GraphTraverser($store);

// BFS traversal
$bfsDepths = $traverser->bfs('u1');
assertTest('GraphTraverser::bfs computes node depths from origin', ($bfsDepths['u1'] ?? -1) === 0 && ($bfsDepths['u2'] ?? -1) === 1 && ($bfsDepths['u3'] ?? -1) === 2);

// DFS traversal
$dfsOrder = $traverser->dfs('u1');
assertTest('GraphTraverser::dfs visits all reachable nodes in depth-first sequence', in_array('u1', $dfsOrder, true) && in_array('u3', $dfsOrder, true));

// Shortest Path (Dijkstra)
$shortestPath = $traverser->shortestPath('u1', 'u3');
assertTest('GraphTraverser::shortestPath computes optimal path and weight', $shortestPath instanceof Path && $shortestPath->getNodeIds() === ['u1', 'u2', 'u3'] && $shortestPath->getTotalWeight() === 3.0);

// All Paths
$allPaths = $traverser->allPaths('u1', 'c1');
assertTest('GraphTraverser::allPaths identifies distinct paths to target', count($allPaths) >= 1 && $allPaths[0] instanceof Path);

// PageRank
$pageRank = new PageRank($store);
$scores = $pageRank->compute();
assertTest('PageRank algorithm calculates authority distribution summing to ~1.0', abs(array_sum($scores) - 1.0) < 0.01);
assertTest('PageRank ranks central hub node (c1/u3) with high authority', isset($scores['c1']) && $scores['c1'] > 0);

// Community Detection (LPA)
$community = new CommunityDetection($store);
$clusters = $community->getClusters();
assertTest('CommunityDetection (LPA) partitions graph into cohesive clusters', count($clusters) >= 1);

// Connected Components
$cc = new ConnectedComponents($store);
$weakComponents = $cc->findWeaklyConnected();
assertTest('ConnectedComponents identifies connected subgraphs', count($weakComponents) === 1 && count($weakComponents[0]) === 4);

// -----------------------------------------------------------------------------
// 6. Knowledge Graph Reasoner & Semantic Triples
// -----------------------------------------------------------------------------
echo "\n6. Testing Knowledge Graph Reasoner & Semantic Triples...\n";

$tripleStore = new RDFTripleStore();
$reasoner = new InferenceEngine($tripleStore);

// Base semantic facts
$reasoner->addTriple('Dhaka', 'isLocatedIn', 'Bangladesh');
$reasoner->addTriple('Bangladesh', 'isLocatedIn', 'Asia');
$reasoner->addTriple('Asia', 'isLocatedIn', 'Earth');

$reasoner->addTriple('Alice', 'isSpouseOf', 'Bob');
$reasoner->addTriple('Charlie', 'parentOf', 'David');

$reasoner->addTriple('Fido', 'rdf:type', 'Dog');
$reasoner->addTriple('Dog', 'rdfs:subClassOf', 'Canine');
$reasoner->addTriple('Canine', 'rdfs:subClassOf', 'Mammal');

assertTest('RDFTripleStore queries triples with wildcards', count($reasoner->queryTriples(predicate: 'isLocatedIn')) === 3);

// Register Semantic Inference Rules
$reasoner->addRule(InferenceRule::transitive('isLocatedIn'));
$reasoner->addRule(InferenceRule::symmetric('isSpouseOf'));
$reasoner->addRule(InferenceRule::inverse('parentOf', 'childOf'));
$reasoner->addRule(InferenceRule::subClass());

$inferredCount = $reasoner->infer();
assertTest('InferenceEngine deduces new semantic facts via rules', $inferredCount > 0);

// Verify Transitive Deduction: Dhaka is located in Asia and Earth
assertTest('InferenceEngine deduces transitive location (Dhaka -> Asia)', $reasoner->hasTriple('Dhaka', 'isLocatedIn', 'Asia'));
assertTest('InferenceEngine deduces multi-hop transitive location (Dhaka -> Earth)', $reasoner->hasTriple('Dhaka', 'isLocatedIn', 'Earth'));

// Verify Symmetric Deduction: Bob is spouse of Alice
assertTest('InferenceEngine deduces symmetric relation (Bob isSpouseOf Alice)', $reasoner->hasTriple('Bob', 'isSpouseOf', 'Alice'));

// Verify Inverse Deduction: David is child of Charlie
assertTest('InferenceEngine deduces inverse relation (David childOf Charlie)', $reasoner->hasTriple('David', 'childOf', 'Charlie'));

// Verify SubClass / Type Inheritance: Fido is a Canine and Mammal
assertTest('InferenceEngine deduces class inheritance (Fido rdf:type Canine)', $reasoner->hasTriple('Fido', 'rdf:type', 'Canine'));
assertTest('InferenceEngine deduces multi-level inheritance (Fido rdf:type Mammal)', $reasoner->hasTriple('Fido', 'rdf:type', 'Mammal'));

// -----------------------------------------------------------------------------
// 7. Master GraphManager Facade, Container & Helpers
// -----------------------------------------------------------------------------
echo "\n7. Testing Master GraphManager Facade, Container & Helpers...\n";

$graphManager = new GraphManager(
    config: [
        'driver' => 'memory',
        'default' => 'social_network',
    ],
    pdo: $pdo
);
$graphManager->setGraph('social_network', $store);

$app->instance('graph', $graphManager);
$app->instance(GraphManager::class, $graphManager);

assertTest('Global graph() helper returns GraphManager instance', graph() instanceof GraphManager);
assertTest('Global graph("social_network") resolves GraphStoreInterface', graph('social_network') instanceof GraphStoreInterface);

// cypher() helper
$helperRes = cypher("MATCH (u:User {name: 'Alice'}) RETURN u.name");
assertTest('cypher() helper executes Cypher query returning QueryResult', $helperRes instanceof QueryResult && $helperRes->first()['u.name'] === 'Alice');

// shortest_path() helper
$helperPath = shortest_path('u1', 'u3');
assertTest('shortest_path() helper finds optimal path', $helperPath instanceof Path && $helperPath->getLength() === 2);

// pagerank() helper
$helperPr = pagerank('social_network');
assertTest('pagerank() helper computes node ranks', !empty($helperPr));

// Export graph to DOT and JSON
$dotGraph = $graphManager->toDot('social_network');
assertTest('GraphManager::toDot generates Graphviz digraph definition', str_contains($dotGraph, 'digraph G') && str_contains($dotGraph, 'FRIEND_OF'));

$jsonGraph = $graphManager->toJson('social_network');
assertTest('GraphManager::toJson generates JSON node-edge serialization', str_contains($jsonGraph, '"node_count": 4') && str_contains($jsonGraph, '"edge_count": 4'));

// -----------------------------------------------------------------------------
// 8. Framework CLI Commands
// -----------------------------------------------------------------------------
echo "\n8. Testing Framework CLI Commands...\n";

$kernel = new \NetFusion\Core\Console\Kernel($app);

// CLI Command: graph:query
ob_start();
$queryExit = $kernel->call('graph:query', ['MATCH (u:User) RETURN u.name', 'social_network']);
$queryOutput = ob_get_clean();
assertTest('GraphQueryCommand executes successfully (exit code 0)', $queryExit === 0);
assertTest('GraphQueryCommand displays table of results', str_contains($queryOutput, 'Alice') && str_contains($queryOutput, 'Records Returned'));

// CLI Command: graph:traverse
ob_start();
$traverseExit = $kernel->call('graph:traverse', ['u1', 'u3', 'social_network']);
$traverseOutput = ob_get_clean();
assertTest('GraphTraverseCommand executes successfully (exit code 0)', $traverseExit === 0);
assertTest('GraphTraverseCommand outputs path length and node sequence', str_contains($traverseOutput, 'u1 -> u2 -> u3') && str_contains($traverseOutput, 'Path Length'));

// CLI Command: graph:stats
ob_start();
$statsExit = $kernel->call('graph:stats', ['social_network']);
$statsOutput = ob_get_clean();
assertTest('GraphStatsCommand executes successfully (exit code 0)', $statsExit === 0);
assertTest('GraphStatsCommand outputs node/edge metrics and PageRank', str_contains($statsOutput, 'Total Nodes: 4') && str_contains($statsOutput, 'Top PageRank Influencers'));

// Clean up test sqlite
if (file_exists($testDbFile)) {
    @unlink($testDbFile);
}

echo "\n========================================================================\n";
echo "  Phase 65 Test Results: {$testsPassed} Passed, {$testsFailed} Failed\n";
echo "========================================================================\n\n";

exit($testsFailed > 0 ? 1 : 0);
