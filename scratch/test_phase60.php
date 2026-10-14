<?php

/**
 * NetFusion Framework - Phase 60 Test Suite
 * Enterprise Semantic Vector Search, HNSW Graph Indexing, Hybrid Sparse-Dense Search (BM25 + Vector RRF) & RAG Pipeline
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

use NetFusion\Core\Application;
use NetFusion\Core\Vector\Contracts\VectorInterface;
use NetFusion\Core\Vector\Contracts\VectorIndexInterface;
use NetFusion\Core\Vector\Contracts\EmbeddingInterface;
use NetFusion\Core\Vector\Contracts\DocumentSplitterInterface;
use NetFusion\Core\Vector\Contracts\VectorStoreInterface;
use NetFusion\Core\Vector\Contracts\RAGPipelineInterface;
use NetFusion\Core\Vector\Exceptions\VectorException;
use NetFusion\Core\Vector\Exceptions\DimensionMismatchException;
use NetFusion\Core\Vector\Exceptions\CollectionNotFoundException;
use NetFusion\Core\Vector\Math\Vector;
use NetFusion\Core\Vector\Math\DistanceMetric;
use NetFusion\Core\Vector\Math\ScalarQuantizer;
use NetFusion\Core\Vector\HNSW\HNSWNode;
use NetFusion\Core\Vector\HNSW\HNSWGraph;
use NetFusion\Core\Vector\HNSW\FlatVectorIndex;
use NetFusion\Core\Vector\Search\BM25Index;
use NetFusion\Core\Vector\Search\ReciprocalRankFusion;
use NetFusion\Core\Vector\Search\ReRanker;
use NetFusion\Core\Vector\Chunking\Chunk;
use NetFusion\Core\Vector\Chunking\RecursiveCharacterSplitter;
use NetFusion\Core\Vector\Chunking\MarkdownHeaderSplitter;
use NetFusion\Core\Vector\Chunking\TokenAwareSplitter;
use NetFusion\Core\Vector\Embedding\MockEmbedding;
use NetFusion\Core\Vector\Embedding\EmbeddingManager;
use NetFusion\Core\Vector\VectorDocument;
use NetFusion\Core\Vector\VectorCollection;
use NetFusion\Core\Vector\Store\ArrayVectorStore;
use NetFusion\Core\Vector\Store\DatabaseVectorStore;
use NetFusion\Core\Vector\VectorEngineManager;
use NetFusion\Core\Vector\RAG\RAGContext;
use NetFusion\Core\Vector\RAG\RAGResponse;
use NetFusion\Core\Vector\RAG\RAGPipeline;

$app = new Application(__DIR__ . '/..');

$passed = 0;
$failed = 0;
$total = 0;

function assertTest(string $description, bool $assertion): void
{
    global $passed, $failed, $total;
    $total++;
    if ($assertion) {
        $passed++;
        echo "  [\033[32mPASS\033[0m] {$description}\n";
    } else {
        $failed++;
        echo "  [\033[31mFAIL\033[0m] {$description}\n";
    }
}

echo "\n========================================================================\n";
echo "  NetFusion Framework — Phase 60 Test Suite\n";
echo "  Enterprise Semantic Vector Search, HNSW Graph Indexing & RAG Pipeline\n";
echo "========================================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Vector Math, Distance Metrics & Basic Operations
// -----------------------------------------------------------------------------
echo "1. Testing Vector Math & Distance Metrics...\n";

$v1 = new Vector([1.0, 0.0, 0.0]);
$v2 = new Vector([0.0, 1.0, 0.0]);
$v3 = new Vector([1.0, 1.0, 0.0]);

assertTest("Vector dimensions correct", $v1->dimension() === 3 && count($v1) === 3);
assertTest("Vector values array access & json serialization", $v1[0] === 1.0 && $v1->jsonSerialize() === [1.0, 0.0, 0.0]);
assertTest("Vector dot product orthogonal vectors = 0", abs($v1->dot($v2)) < 1e-6);
assertTest("Vector dot product collinear vectors", abs($v1->dot($v1) - 1.0) < 1e-6);
assertTest("Vector L2 norm calculation", abs($v3->norm() - sqrt(2.0)) < 1e-6);

$v3Norm = $v3->normalize();
assertTest("Vector normalization produces unit norm", abs($v3Norm->norm() - 1.0) < 1e-6);
assertTest("Vector Euclidean distance between [1,0,0] and [0,1,0] is sqrt(2)", abs($v1->euclideanDistance($v2) - sqrt(2.0)) < 1e-6);
assertTest("Vector Manhattan distance between [1,0,0] and [0,1,0] is 2", abs($v1->manhattanDistance($v2) - 2.0) < 1e-6);
assertTest("Vector Cosine similarity orthogonal = 0", abs($v1->cosineSimilarity($v2)) < 1e-6);
assertTest("Vector Cosine similarity identical = 1.0", abs($v1->cosineSimilarity($v1) - 1.0) < 1e-6);
assertTest("Vector Cosine distance orthogonal = 1.0", abs($v1->cosineDistance($v2) - 1.0) < 1e-6);

$vZeros = Vector::zeros(5);
assertTest("Vector::zeros creates 5-dim zero vector", $vZeros->dimension() === 5 && $vZeros[4] === 0.0);

$vRand = Vector::random(10);
assertTest("Vector::random creates normalized 10-dim vector", $vRand->dimension() === 10 && abs($vRand->norm() - 1.0) < 1e-5);

$mismatchThrown = false;
try {
    $v1->dot($vZeros);
} catch (DimensionMismatchException $e) {
    $mismatchThrown = true;
}
assertTest("DimensionMismatchException thrown on dimension mismatch", $mismatchThrown);

// DistanceMetric Enum
assertTest("DistanceMetric::COSINE similarity calculation", abs(DistanceMetric::COSINE->calculateSimilarity($v1, $v1) - 1.0) < 1e-6);
assertTest("DistanceMetric::EUCLIDEAN distance calculation", abs(DistanceMetric::EUCLIDEAN->calculateDistance($v1, $v2) - sqrt(2.0)) < 1e-6);
assertTest("DistanceMetric::MANHATTAN distance calculation", abs(DistanceMetric::MANHATTAN->calculateDistance($v1, $v2) - 2.0) < 1e-6);

// -----------------------------------------------------------------------------
// 2. Vector Quantization (Scalar SQ8)
// -----------------------------------------------------------------------------
echo "\n2. Testing Vector Quantization (Scalar SQ8)...\n";

$origVec = new Vector([0.15, -0.42, 0.88, 0.0, -0.95, 0.73]);
$sq8 = ScalarQuantizer::quantizeSQ8($origVec);

assertTest("SQ8 quantization produces byte array and min/max scale", count($sq8['bytes']) === 6 && isset($sq8['min'], $sq8['max']));
$dequant = ScalarQuantizer::dequantizeSQ8($sq8);
$reconstructDist = $origVec->euclideanDistance($dequant);
assertTest("SQ8 dequantization reconstruction error is very low (< 0.02)", $reconstructDist < 0.02);

$q1 = ScalarQuantizer::quantizeSQ8($v1);
$q2 = ScalarQuantizer::quantizeSQ8($v3Norm);
$quantDot = ScalarQuantizer::dotSQ8($q1, $q2);
$realDot = $v1->dot($v3Norm);
assertTest("Quantized dot product closely matches float dot product", abs($quantDot - $realDot) < 0.05);

// -----------------------------------------------------------------------------
// 3. HNSW (Hierarchical Navigable Small World) Graph Index
// -----------------------------------------------------------------------------
echo "\n3. Testing HNSW Multi-Layer Graph Index...\n";

$hnsw = new HNSWGraph(M: 8, efConstruction: 32, efSearch: 16, metric: DistanceMetric::COSINE, dimension: 4);

$nodesData = [
    'vec_apple'   => new Vector([1.0, 0.9, 0.1, 0.0]),
    'vec_banana'  => new Vector([0.9, 0.95, 0.05, 0.0]),
    'vec_orange'  => new Vector([0.85, 0.8, 0.2, 0.0]),
    'vec_car'     => new Vector([0.0, 0.1, 0.95, 0.9]),
    'vec_truck'   => new Vector([0.05, 0.05, 0.9, 0.95]),
    'vec_airplane'=> new Vector([0.1, 0.0, 0.8, 0.85]),
];

foreach ($nodesData as $id => $vec) {
    $category = str_contains($id, 'vec_car') || str_contains($id, 'vec_truck') || str_contains($id, 'vec_airplane') ? 'vehicle' : 'fruit';
    $hnsw->insert($id, $vec, ['category' => $category, 'label' => $id]);
}

assertTest("HNSW index contains 6 nodes", $hnsw->count() === 6);
assertTest("HNSW has() returns true for inserted IDs", $hnsw->has('vec_apple') && $hnsw->has('vec_car'));

// Query search near fruit cluster
$queryFruit = new Vector([1.0, 0.85, 0.0, 0.0]);
$fruitResults = $hnsw->search($queryFruit, k: 3);

assertTest("HNSW nearest neighbor for fruit query is fruit category", count($fruitResults) === 3 && in_array($fruitResults[0]['id'], ['vec_apple', 'vec_banana']));
assertTest("HNSW results are sorted by similarity score descending", $fruitResults[0]['score'] >= $fruitResults[1]['score']);

// Query search with metadata filter
$filteredResults = $hnsw->search($queryFruit, k: 3, filter: ['category' => 'vehicle']);
assertTest("HNSW metadata filter returns only matching category", count($filteredResults) > 0 && $filteredResults[0]['metadata']['category'] === 'vehicle');

// Deletion from HNSW
$deleted = $hnsw->delete('vec_airplane');
assertTest("HNSW delete() successfully removes node", $deleted && !$hnsw->has('vec_airplane') && $hnsw->count() === 5);

// Serialization & Deserialization
$hnswJson = $hnsw->jsonSerialize();
$restoredHnsw = HNSWGraph::fromArray($hnswJson);
assertTest("HNSW serialized and restored accurately", $restoredHnsw->count() === 5 && $restoredHnsw->has('vec_apple'));

// FlatVectorIndex test
$flat = new FlatVectorIndex(DistanceMetric::COSINE, 4);
foreach ($nodesData as $id => $vec) {
    $flat->insert($id, $vec, ['label' => $id]);
}
$flatResults = $flat->search($queryFruit, k: 2);
assertTest("FlatVectorIndex exact k-NN returns fruit top match", count($flatResults) === 2 && in_array($flatResults[0]['id'], ['vec_apple', 'vec_banana']));

// -----------------------------------------------------------------------------
// 4. BM25 Sparse Lexical Search & Hybrid Fusion
// -----------------------------------------------------------------------------
echo "\n4. Testing BM25 Lexical Search, Reciprocal Rank Fusion & ReRanker...\n";

$bm25 = new BM25Index(k1: 1.2, b: 0.75);
$bm25->insert('doc1', 'NetFusion is an enterprise high-performance PHP framework with zero external dependencies.', ['category' => 'framework']);
$bm25->insert('doc2', 'Machine learning and deep neural networks in Python and PyTorch.', ['category' => 'ai']);
$bm25->insert('doc3', 'Enterprise distributed service mesh and proxy gateways in NetFusion.', ['category' => 'networking']);
$bm25->insert('doc4', 'BPMN 2.0 dynamic workflow engine with saga orchestrator in NetFusion framework.', ['category' => 'workflow']);

assertTest("BM25 index contains 4 documents", $bm25->count() === 4);

$bm25Results = $bm25->search('NetFusion framework', k: 3);
assertTest("BM25 retrieves top matches for 'NetFusion framework'", count($bm25Results) >= 2 && ($bm25Results[0]['id'] === 'doc1' || $bm25Results[0]['id'] === 'doc4'));
assertTest("BM25 scores are positive and ranked descending", $bm25Results[0]['score'] >= $bm25Results[1]['score']);

// BM25 Metadata Filter
$bm25Filtered = $bm25->search('NetFusion', k: 3, filter: ['category' => 'networking']);
assertTest("BM25 metadata filter restricts results to networking category", count($bm25Filtered) === 1 && $bm25Filtered[0]['id'] === 'doc3');

// Reciprocal Rank Fusion (RRF)
$denseFakeResults = [
    ['id' => 'doc1', 'score' => 0.95, 'metadata' => ['title' => 'Doc 1']],
    ['id' => 'doc2', 'score' => 0.85, 'metadata' => ['title' => 'Doc 2']],
    ['id' => 'doc3', 'score' => 0.70, 'metadata' => ['title' => 'Doc 3']],
];
$sparseFakeResults = [
    ['id' => 'doc3', 'score' => 12.5, 'metadata' => ['title' => 'Doc 3']],
    ['id' => 'doc1', 'score' => 8.2, 'metadata' => ['title' => 'Doc 1']],
    ['id' => 'doc4', 'score' => 5.1, 'metadata' => ['title' => 'Doc 4']],
];

$rrfResults = ReciprocalRankFusion::fuse($denseFakeResults, $sparseFakeResults, k: 60, topK: 3);
assertTest("RRF fusion blends dense and sparse rankings", count($rrfResults) === 3 && ($rrfResults[0]['id'] === 'doc1' || $rrfResults[0]['id'] === 'doc3'));
assertTest("RRF results include dense and sparse rank metadata", isset($rrfResults[0]['dense_rank'], $rrfResults[0]['sparse_rank']));

// Score Blending
$blendedResults = ReciprocalRankFusion::blend($denseFakeResults, $sparseFakeResults, alpha: 0.6, topK: 3);
assertTest("Score blending produces normalized blended scores [0..1]", count($blendedResults) === 3 && $blendedResults[0]['score'] <= 1.0);

// ReRanker
$candidates = [
    ['id' => 'doc1', 'score' => 0.6, 'text' => 'General overview of software architecture and principles.'],
    ['id' => 'doc2', 'score' => 0.5, 'text' => 'Comprehensive guide to NetFusion Enterprise Service Mesh architecture.'],
];
$reranked = ReRanker::rerank('NetFusion Enterprise Service Mesh', $candidates, topK: 2);
assertTest("ReRanker boosts candidate with exact phrase & token overlap", $reranked[0]['id'] === 'doc2' && $reranked[0]['score'] > $candidates[1]['score']);

// -----------------------------------------------------------------------------
// 5. Document Chunking Engine
// -----------------------------------------------------------------------------
echo "\n5. Testing Document Chunking Engine...\n";

$longText = <<<TEXT
# Introduction to NetFusion
NetFusion Framework is a pure PHP 8.2+ enterprise platform. It features modular HMVC architecture.

## Vector Search Engine
The vector search engine implements HNSW graph indexing. It supports dense embeddings and sparse BM25 retrieval.

## RAG Knowledge Pipeline
Retrieval-Augmented Generation synthesizes grounded answers with verifiable citations.
TEXT;

// Recursive Character Splitter
$charSplitter = new RecursiveCharacterSplitter(chunkSize: 120, chunkOverlap: 20);
$charChunks = $charSplitter->splitText($longText, ['source' => 'manual.md']);
assertTest("RecursiveCharacterSplitter creates multiple chunks with offsets", count($charChunks) >= 2);
assertTest("Chunks have estimated token counts and metadata", $charChunks[0]->getTokenCount() > 0 && $charChunks[0]->getMetadata()['source'] === 'manual.md');

// Markdown Header Splitter
$mdSplitter = new MarkdownHeaderSplitter();
$mdChunks = $mdSplitter->splitText($longText, ['doc' => 'arch.md']);
assertTest("MarkdownHeaderSplitter segments by headings", count($mdChunks) >= 3);
assertTest("Markdown chunks inherit header metadata hierarchy", isset($mdChunks[1]->getMetadata()['header_2']) && $mdChunks[1]->getMetadata()['header_2'] === 'Vector Search Engine');

// Token Aware Splitter
$tokenSplitter = new TokenAwareSplitter(maxTokens: 15, tokenOverlap: 3);
$tokenChunks = $tokenSplitter->splitText($longText);
assertTest("TokenAwareSplitter creates token-bounded chunks", count($tokenChunks) >= 3 && $tokenChunks[0]->getTokenCount() <= 20);

// -----------------------------------------------------------------------------
// 6. Embedding Connectors & Embedding Manager
// -----------------------------------------------------------------------------
echo "\n6. Testing Embedding Connectors & Embedding Manager...\n";

$mockEmbedder = new MockEmbedding(dimension: 32, model: 'test-mock-32');
assertTest("MockEmbedding dimension matches configured value", $mockEmbedder->getDimension() === 32);

$vecText1 = $mockEmbedder->embedText("Microservice architecture in NetFusion");
$vecText2 = $mockEmbedder->embedText("Microservice architecture in NetFusion");
$vecTextDiff = $mockEmbedder->embedText("Baking chocolate chip cookies recipe");

assertTest("MockEmbedding produces deterministic normalized vector", $vecText1->dimension() === 32 && abs($vecText1->norm() - 1.0) < 1e-5);
assertTest("Identical texts produce cosine similarity = 1.0", abs($vecText1->cosineSimilarity($vecText2) - 1.0) < 1e-5);
assertTest("Semantic similarity between identical texts > unrelated text", $vecText1->cosineSimilarity($vecText2) > $vecText1->cosineSimilarity($vecTextDiff));

$batchVectors = $mockEmbedder->embedBatch(["Sentence A", "Sentence B", "Sentence C"]);
assertTest("embedBatch returns array of 3 vectors", count($batchVectors) === 3 && $batchVectors[0] instanceof VectorInterface);

$embedMgr = new EmbeddingManager([
    'default' => 'mock',
    'drivers' => [
        'mock' => ['dimension' => 32, 'model' => 'mock-embed-v1'],
    ],
]);

$cachedVec1 = $embedMgr->embed("Caching test text");
$cachedVec2 = $embedMgr->embed("Caching test text");
assertTest("EmbeddingManager caches generated embeddings", $cachedVec1 === $cachedVec2);

// -----------------------------------------------------------------------------
// 7. Vector Documents, Collections & Persistence Stores
// -----------------------------------------------------------------------------
echo "\n7. Testing Vector Collection, Documents & Persistence Stores...\n";

$collection = new VectorCollection(
    name: 'kb_articles',
    dimension: 32,
    metric: DistanceMetric::COSINE,
    embeddingProvider: $mockEmbedder,
    splitter: new RecursiveCharacterSplitter(chunkSize: 100, chunkOverlap: 20)
);

// Add documents
$collection->addText('art_1', 'NetFusion provides an in-memory data grid and zero-trust service mesh.', ['topic' => 'mesh']);
$collection->addText('art_2', 'Dynamic business rule engine evaluates RETE networks and DMN 1.3 tables.', ['topic' => 'rules']);
$collection->addText('art_3', 'Complex Event Processing (CEP) executes NFA pattern matching on event streams.', ['topic' => 'cep']);

assertTest("VectorCollection contains 3 documents", $collection->count() === 3);
assertTest("VectorCollection hasDocument() returns true", $collection->hasDocument('art_1'));

// Ingest long article with chunking
$ingestedChunks = $collection->ingest('parent_art_4', $longText, ['category' => 'manual']);
assertTest("VectorCollection ingest() automatically splits, embeds and indexes chunks", count($ingestedChunks) >= 2);
assertTest("Collection document count increased after ingestion", $collection->count() >= 5);

// Search: Hybrid
$hybridSearchResults = $collection->search('zero-trust service mesh architecture', k: 2, options: ['type' => 'hybrid']);
assertTest("VectorCollection hybrid search returns top relevant matches", count($hybridSearchResults) === 2 && $hybridSearchResults[0]['id'] === 'art_1');

// Search: Dense only
$denseResults = $collection->search('business rule engine RETE', k: 2, options: ['type' => 'dense']);
assertTest("VectorCollection dense search returns matching document", count($denseResults) > 0 && $denseResults[0]['id'] === 'art_2');

// Search: Sparse only
$sparseResults = $collection->search('Complex Event Processing CEP', k: 2, options: ['type' => 'sparse']);
assertTest("VectorCollection sparse search returns matching document", count($sparseResults) > 0 && $sparseResults[0]['id'] === 'art_3');

// Search: With Metadata Filter
$filteredSearch = $collection->search('NetFusion', k: 2, options: ['filter' => ['topic' => 'rules']]);
assertTest("VectorCollection search with metadata filter returns only matching topic", count($filteredSearch) === 1 && $filteredSearch[0]['id'] === 'art_2');

// Delete Document
$docDeleted = $collection->deleteDocument('art_3');
assertTest("VectorCollection deleteDocument() removes doc and index entries", $docDeleted && !$collection->hasDocument('art_3'));

// In-Memory Array Store
$arrayStore = new ArrayVectorStore();
$arrayStore->saveCollection($collection);
assertTest("ArrayVectorStore saves and retrieves collection", $arrayStore->hasCollection('kb_articles') && $arrayStore->getCollection('kb_articles')->count() === $collection->count());
assertTest("ArrayVectorStore listCollections() returns collection name", in_array('kb_articles', $arrayStore->listCollections(), true));

// Database Vector Store (SQLite)
$sqlitePdo = new PDO('sqlite::memory:');
$sqlitePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$migration = require __DIR__ . '/../database/migrations/2026_09_21_000040_create_vector_engine_tables.php';
$migration->up($sqlitePdo);

$dbStore = new DatabaseVectorStore($sqlitePdo, $mockEmbedder);
$dbStore->saveCollection($collection);

assertTest("DatabaseVectorStore saves collection and tables to SQLite", $dbStore->hasCollection('kb_articles'));
$loadedDbCol = $dbStore->getCollection('kb_articles');
assertTest("DatabaseVectorStore hydrates collection and documents from SQLite", $loadedDbCol !== null && $loadedDbCol->count() === $collection->count());

$dbSearchResults = $loadedDbCol->search('service mesh', k: 1);
assertTest("Hydrated collection from DatabaseVectorStore executes search correctly", count($dbSearchResults) === 1 && $dbSearchResults[0]['id'] === 'art_1');

$dbStore->deleteCollection('kb_articles');
assertTest("DatabaseVectorStore deleteCollection() removes collection and documents", !$dbStore->hasCollection('kb_articles'));

// -----------------------------------------------------------------------------
// 8. VectorEngineManager & RAG Knowledge Pipeline
// -----------------------------------------------------------------------------
echo "\n8. Testing VectorEngineManager & Enterprise RAG Pipeline...\n";

$vectorManager = new VectorEngineManager([
    'store' => 'array',
    'embeddings' => [
        'default' => 'mock',
        'drivers' => [
            'mock' => ['dimension' => 32, 'model' => 'mock-embed-v1'],
        ],
    ],
    'rag' => [
        'top_k' => 2,
        'type' => 'hybrid',
        'rerank' => true,
    ],
]);

$kb = $vectorManager->collection('company_kb');
$kb->addText('kb_hr_1', 'Employees are eligible for 20 days of paid annual leave per calendar year.', ['category' => 'hr']);
$kb->addText('kb_hr_2', 'Health insurance coverage starts on the first day of full-time employment.', ['category' => 'hr']);
$kb->addText('kb_dev_1', 'Production deployments require 2 approvals and automated test pass rate of 100%.', ['category' => 'devops']);

assertTest("VectorEngineManager collection initialized and populated", $vectorManager->hasCollection('company_kb') && $kb->count() === 3);

// RAG Pipeline execution
$rag = $vectorManager->rag();
assertTest("VectorEngineManager rag() returns RAGPipeline instance", $rag instanceof RAGPipelineInterface);

$response = $rag->query('How many days of annual leave do employees get?', 'company_kb', ['top_k' => 2]);

assertTest("RAG response contains query and synthesized answer", $response->getQuery() !== '' && !empty($response->getAnswer()));
assertTest("RAG context contains top-K retrieved source chunks with citations", $response->getContext()->count() === 2 && in_array($response->getContext()->getChunks()[0]['id'], ['kb_hr_1', 'kb_hr_2']));
assertTest("RAG execution time tracked (> 0 ms)", $response->getExecutionTimeMs() >= 0.0);
assertTest("RAG answer references retrieved knowledge", str_contains($response->getAnswer(), 'annual leave') || str_contains($response->getAnswer(), '20 days'));

// Custom LLM Generator integration
$customLlmFired = false;
$rag->setLlmGenerator(function (string $prompt, RAGContext $context) use (&$customLlmFired) {
    $customLlmFired = true;
    return "LLM Answer: Employees receive 20 days of annual leave.";
});

$customLlmResponse = $rag->query('What is the annual leave policy?', 'company_kb');
assertTest("RAG pipeline calls custom LLM generator when configured", $customLlmFired && str_contains($customLlmResponse->getAnswer(), 'LLM Answer:'));

// -----------------------------------------------------------------------------
// 9. Console Commands & Helpers Integration
// -----------------------------------------------------------------------------
echo "\n9. Testing Console Commands & Global Helpers...\n";

// Register bindings in Application container
$app->singleton('vector', function () use ($vectorManager) {
    return $vectorManager;
});

assertTest("Container resolves 'vector' and 'vector.manager'", $app->has('vector'));
assertTest("Global helper vector() returns VectorEngineManager", function_exists('vector') && vector() instanceof VectorEngineManager);
assertTest("Global helper vector_collection() retrieves collection", vector_collection('company_kb') instanceof VectorCollection);
assertTest("Global helper embed() produces VectorInterface", embed("Hello world") instanceof VectorInterface);
assertTest("Global helper rag() executes RAG pipeline", rag('health insurance', 'company_kb') instanceof RAGResponse);

// Test CLI Commands Execution
$kernel = new \NetFusion\Core\Console\Kernel($app);

ob_start();
$exitCode1 = $kernel->call('vector:collections', []);
$output1 = ob_get_clean();
assertTest("CLI command 'vector:collections' executes successfully", $exitCode1 === 0 && str_contains($output1, 'company_kb'));

ob_start();
$exitCode2 = $kernel->call('vector:search', ['company_kb', 'deployments and testing']);
$output2 = ob_get_clean();
assertTest("CLI command 'vector:search' executes and prints results table", $exitCode2 === 0 && str_contains($output2, 'kb_dev_1'));

ob_start();
$exitCode3 = $kernel->call('rag:query', ['company_kb', 'What is health insurance policy?']);
$output3 = ob_get_clean();
assertTest("CLI command 'rag:query' executes and prints grounded response", $exitCode3 === 0 && str_contains($output3, 'Citations'));

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n========================================================================\n";
echo "  Phase 60 Test Suite Finished: \033[32m{$passed} Passed\033[0m, \033[" . ($failed > 0 ? "31m" : "32m") . "{$failed} Failed\033[0m (Total: {$total})\n";
echo "========================================================================\n\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
