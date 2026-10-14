<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - Phase 91 Test Suite
 * |--------------------------------------------------------------------------
 * |
 * | Unit tests for Multi-Modal Vector Knowledge Graph (GraphRAG),
 * | Hybrid Semantic RAG & Dynamic Graph Reranking Engine.
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Application;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\GraphRag\Chunking\DocumentChunker;
use NetFusion\Core\GraphRag\Entity\KnowledgeEdge;
use NetFusion\Core\GraphRag\Entity\KnowledgeNode;
use NetFusion\Core\GraphRag\Enums\FusionStrategy;
use NetFusion\Core\GraphRag\Enums\ModalityType;
use NetFusion\Core\GraphRag\Enums\SearchStrategy;
use NetFusion\Core\GraphRag\Exceptions\EmbeddingMismatchException;
use NetFusion\Core\GraphRag\Graph\VectorKnowledgeGraph;
use NetFusion\Core\GraphRag\GraphRagManager;
use NetFusion\Core\GraphRag\Index\Bm25Index;
use NetFusion\Core\GraphRag\Index\VectorSimilarity;
use NetFusion\Core\GraphRag\Prompt\ContextSynthesizer;
use NetFusion\Core\GraphRag\Reranking\GraphReranker;
use NetFusion\Core\GraphRag\Retrieval\HybridRetriever;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $testName): void
{
    global $passed, $failed;
    if ($condition) {
        echo "[\033[32mPASS\033[0m] {$testName}\n";
        $passed++;
    } else {
        echo "[\033[31mFAIL\033[0m] {$testName}\n";
        $failed++;
    }
}

echo "========================================================\n";
echo " NetFusion Phase 91: Multi-Modal GraphRAG Test Suite    \n";
echo "========================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Enums & Exceptions
// -----------------------------------------------------------------------------
assertTest(ModalityType::TEXT->value === 'text', "ModalityType TEXT value matches");
assertTest(ModalityType::IMAGE->value === 'image', "ModalityType IMAGE value matches");
assertTest(ModalityType::DOCUMENT->value === 'document', "ModalityType DOCUMENT value matches");
assertTest(FusionStrategy::RECIPROCAL_RANK_FUSION->value === 'rrf', "FusionStrategy RRF value matches");
assertTest(FusionStrategy::WEIGHTED_SCORE->value === 'weighted_score', "FusionStrategy WEIGHTED_SCORE value matches");
assertTest(SearchStrategy::HYBRID_TRIFECTA->value === 'hybrid_trifecta', "SearchStrategy HYBRID_TRIFECTA matches");

// -----------------------------------------------------------------------------
// 2. Multi-Modal Knowledge Node & Edge Entities
// -----------------------------------------------------------------------------
$node1 = new KnowledgeNode(
    id: 'node_google',
    label: 'Company',
    content: 'Google is a global technology company specializing in search and cloud computing.',
    modality: ModalityType::TEXT,
    vector: [0.12, 0.45, 0.78, 0.05],
    properties: ['industry' => 'Tech', 'hq' => 'Mountain View'],
    confidence: 0.98
);

assertTest($node1->getId() === 'node_google', "KnowledgeNode ID matches");
assertTest($node1->getLabel() === 'Company', "KnowledgeNode Label matches");
assertTest($node1->getModality() === ModalityType::TEXT, "KnowledgeNode modality is TEXT");
assertTest($node1->hasVector() === true, "KnowledgeNode has dense vector");
assertTest(count($node1->getVector()) === 4, "KnowledgeNode vector dimension is 4");
assertTest($node1->getProperty('industry') === 'Tech', "KnowledgeNode stores metadata properties");

$node2 = new KnowledgeNode(
    id: 'node_deepmind',
    label: 'ResearchLab',
    content: 'DeepMind is an artificial intelligence research laboratory based in London.',
    modality: ModalityType::TEXT,
    vector: [0.15, 0.48, 0.72, 0.02],
    properties: ['focus' => 'Artificial Intelligence']
);

$edgeAcquisition = new KnowledgeEdge(
    sourceId: 'node_google',
    targetId: 'node_deepmind',
    relationship: 'ACQUIRED',
    weight: 0.95,
    properties: ['year' => 2014]
);

assertTest($edgeAcquisition->getSourceId() === 'node_google', "KnowledgeEdge source matches");
assertTest($edgeAcquisition->getTargetId() === 'node_deepmind', "KnowledgeEdge target matches");
assertTest($edgeAcquisition->getRelationship() === 'ACQUIRED', "KnowledgeEdge relationship matches");
assertTest($edgeAcquisition->getWeight() === 0.95, "KnowledgeEdge weight matches");
assertTest($edgeAcquisition->getProperty('year') === 2014, "KnowledgeEdge stores edge properties");

// -----------------------------------------------------------------------------
// 3. Document Chunker & Semantic Keyword Extraction
// -----------------------------------------------------------------------------
$article = "NetFusion is a next-generation PHP framework. It provides modular HMVC architecture. " .
    "The framework was developed by DotNetBD. It supports distributed computing and real-time streaming.";

$chunks = DocumentChunker::chunkText($article, chunkSize: 80, overlap: 20);
assertTest(count($chunks) >= 2, "DocumentChunker splits long text into multiple overlapping chunks");
assertTest(isset($chunks[0]['text']) && isset($chunks[0]['offset']), "Chunk contains text and offset metadata");

$extractedEntities = DocumentChunker::extractEntityKeywords($article);
assertTest(in_array('NetFusion', $extractedEntities), "Chunker extracts entity 'NetFusion'");
assertTest(in_array('DotNetBD', $extractedEntities), "Chunker extracts entity 'DotNetBD'");

// -----------------------------------------------------------------------------
// 4. Mathematical Vector Similarity Metrics
// -----------------------------------------------------------------------------
$vecA = [1.0, 0.0, 0.0];
$vecB = [1.0, 0.0, 0.0];
$vecC = [0.0, 1.0, 0.0];

$cosIdentical = VectorSimilarity::cosine($vecA, $vecB);
$cosOrthogonal = VectorSimilarity::cosine($vecA, $vecC);
assertTest(abs($cosIdentical - 1.0) < 0.0001, "VectorSimilarity cosine identical vectors score is 1.0");
assertTest(abs($cosOrthogonal - 0.0) < 0.0001, "VectorSimilarity cosine orthogonal vectors score is 0.0");

$dotScore = VectorSimilarity::dotProduct($vecA, $vecB);
assertTest($dotScore === 1.0, "VectorSimilarity dot product is accurate");

$euclidDist = VectorSimilarity::euclidean([0.0, 0.0], [3.0, 4.0]);
assertTest(abs($euclidDist - 5.0) < 0.0001, "VectorSimilarity euclidean distance 3-4-5 triangle is 5.0");

$dimensionMismatchCaught = false;
try {
    VectorSimilarity::cosine([1.0, 2.0], [1.0, 2.0, 3.0]);
} catch (EmbeddingMismatchException) {
    $dimensionMismatchCaught = true;
}
assertTest($dimensionMismatchCaught, "VectorSimilarity throws EmbeddingMismatchException on dimension mismatch");

// -----------------------------------------------------------------------------
// 5. Inverted Okapi BM25 Lexical Index
// -----------------------------------------------------------------------------
$bm25 = new Bm25Index(k1: 1.5, b: 0.75);
$bm25->addDocument('doc1', 'Quantum computing uses qubits and superposition');
$bm25->addDocument('doc2', 'Deep learning uses artificial neural networks and backpropagation');
$bm25->addDocument('doc3', 'Quantum physics and quantum entanglement in modern physics');

assertTest($bm25->count() === 3, "Bm25Index contains 3 documents");

$quantumSearchResults = $bm25->search('quantum', topK: 2);
assertTest(count($quantumSearchResults) === 2, "Bm25Index finds top 2 documents for query 'quantum'");
assertTest(isset($quantumSearchResults['doc3']) || isset($quantumSearchResults['doc1']), "Top BM25 result is quantum document");
assertTest(!isset($quantumSearchResults['doc2']), "BM25 excludes unrelated neural network document");

// Document removal
$bm25->removeDocument('doc1');
assertTest($bm25->count() === 2, "Bm25Index successfully removes document and updates counts");

// -----------------------------------------------------------------------------
// 6. Vector Knowledge Graph Topology & Neighborhood Expansion
// -----------------------------------------------------------------------------
$graph = new VectorKnowledgeGraph();
$graph->addNode($node1);
$graph->addNode($node2);
$graph->addEdge($edgeAcquisition);

// Add 3rd node connected to DeepMind
$node3 = new KnowledgeNode(
    id: 'node_alphago',
    label: 'Product',
    content: 'AlphaGo is an AI program developed by DeepMind to play Go.',
    vector: [0.18, 0.50, 0.70, 0.01]
);
$edgeDevelopment = new KnowledgeEdge('node_deepmind', 'node_alphago', 'DEVELOPED', 0.99);
$graph->addNode($node3);
$graph->addEdge($edgeDevelopment);

assertTest($graph->countNodes() === 3, "VectorKnowledgeGraph contains 3 nodes");
assertTest($graph->countEdges() === 2, "VectorKnowledgeGraph contains 2 edges");
assertTest($graph->getNodeDegree('node_deepmind') === 2, "Node degree for DeepMind is 2 (1 incoming, 1 outgoing)");

// k-Hop Expansion from Google
$expansion1Hop = $graph->kHopExpansion('node_google', depth: 1);
assertTest(count($expansion1Hop['nodes']) === 2, "1-Hop expansion from Google reaches 2 nodes (Google + DeepMind)");
assertTest(isset($expansion1Hop['nodes']['node_deepmind']), "1-Hop includes DeepMind");
assertTest(!isset($expansion1Hop['nodes']['node_alphago']), "1-Hop does not reach 2-hop AlphaGo");

$expansion2Hop = $graph->kHopExpansion('node_google', depth: 2);
assertTest(count($expansion2Hop['nodes']) === 3, "2-Hop expansion from Google reaches all 3 nodes (Google, DeepMind, AlphaGo)");
assertTest(isset($expansion2Hop['nodes']['node_alphago']), "2-Hop reaches AlphaGo");

// Dense Neighbors Search
$denseHits = $graph->findDenseNeighbors([0.15, 0.48, 0.72, 0.02], limit: 2);
assertTest(count($denseHits) === 2, "VectorKnowledgeGraph dense vector search returns 2 closest neighbors");
assertTest($denseHits[0]['node']->getId() === 'node_deepmind', "Closest dense neighbor is DeepMind (exact match)");

// Sparse Lexical Search
$sparseHits = $graph->findSparseNeighbors('AlphaGo Go', limit: 1);
assertTest(count($sparseHits) === 1, "VectorKnowledgeGraph sparse search finds 1 hit");
assertTest($sparseHits[0]['node']->getId() === 'node_alphago', "Sparse hit is AlphaGo node");

// -----------------------------------------------------------------------------
// 7. Tri-Way Hybrid Retrieval (RRF & Weighted Score Fusion)
// -----------------------------------------------------------------------------
$retriever = new HybridRetriever($graph, rrfConstant: 60);

// Search with both vector and text
$retrievedRrf = $retriever->retrieve(
    query: 'artificial intelligence research Go',
    queryVector: [0.15, 0.48, 0.72, 0.02],
    limit: 3,
    strategy: FusionStrategy::RECIPROCAL_RANK_FUSION
);

assertTest(count($retrievedRrf) === 3, "HybridRetriever returns top 3 candidates via RRF");
assertTest($retrievedRrf[0]['rank'] === 1, "First retrieved item has rank 1");
assertTest(in_array('dense_vector', $retrievedRrf[0]['matchTypes']) || in_array('sparse_bm25', $retrievedRrf[0]['matchTypes']), "Retrieved item tracks match types");

// Test Weighted Score Fusion
$retrievedWeighted = $retriever->retrieve(
    query: 'artificial intelligence research Go',
    queryVector: [0.15, 0.48, 0.72, 0.02],
    limit: 3,
    strategy: FusionStrategy::WEIGHTED_SCORE
);
assertTest(count($retrievedWeighted) === 3, "HybridRetriever returns top 3 candidates via Weighted Score fusion");

// -----------------------------------------------------------------------------
// 8. Dynamic Graph Reranker (Centrality Bonus + MMR Diversity)
// -----------------------------------------------------------------------------
$reranker = new GraphReranker($graph, centralityBonus: 0.20);
$reranked = $reranker->rerank($retrievedRrf, limit: 3, diversityLambda: 0.75);

assertTest(count($reranked) === 3, "GraphReranker returns top 3 reranked nodes");
assertTest(isset($reranked[0]['rerankScore']), "Reranked items contain rerankScore");
assertTest($reranked[0]['rank'] === 1, "Reranked list has normalized rank 1");

// -----------------------------------------------------------------------------
// 9. Context Synthesizer for LLM Grounding
// -----------------------------------------------------------------------------
$synthesizedContext = ContextSynthesizer::synthesize($reranked, $graph, 'Who developed AlphaGo?');
assertTest(str_contains($synthesizedContext, '=== GROUNDED KNOWLEDGE GRAPH CONTEXT ==='), "Context contains header banner");
assertTest(str_contains($synthesizedContext, 'Query: Who developed AlphaGo?'), "Context includes original query");
assertTest(str_contains($synthesizedContext, '[Graph Entity Relationships & Topology]'), "Context formats graph relationships");
assertTest(str_contains($synthesizedContext, 'node_deepmind') && str_contains($synthesizedContext, 'DEVELOPED'), "Context includes relational edge");
assertTest(str_contains($synthesizedContext, '[Verified Knowledge Excerpts]'), "Context formats verified excerpts");

// -----------------------------------------------------------------------------
// 10. GraphRagManager Master Facade
// -----------------------------------------------------------------------------
$manager = new GraphRagManager();
$docNodes = $manager->ingestDocument('doc_netfusion', "NetFusion is a high-performance framework. It supports PHP 8.2 with zero dependencies.");
assertTest(count($docNodes) >= 1, "GraphRagManager::ingestDocument splits document and returns chunk nodes");
assertTest($manager->getGraph()->hasNode('doc_netfusion'), "Graph contains parent document node");

$manager->link('doc_netfusion', 'node_google', 'INFLUENCED_BY', 0.85);
assertTest($manager->getGraph()->countEdges() >= 1, "GraphRagManager::link connects nodes with relationship");

$queryResults = $manager->retrieve('high-performance framework PHP', limit: 2, rerank: true);
assertTest(count($queryResults) >= 1, "GraphRagManager::retrieve returns reranked items");

$llmPromptContext = $manager->synthesizeContext('Tell me about NetFusion framework', limit: 2);
assertTest(is_string($llmPromptContext) && str_contains($llmPromptContext, 'NetFusion'), "GraphRagManager::synthesizeContext generates formatted prompt text");

$exportedGraph = $manager->exportGraph();
assertTest(count($exportedGraph['nodes']) >= 4, "GraphRagManager::exportGraph exports nodes");
assertTest(count($exportedGraph['edges']) >= 1, "GraphRagManager::exportGraph exports edges");

// -----------------------------------------------------------------------------
// 11. Application Container & Global Helpers
// -----------------------------------------------------------------------------
$app = new Application(__DIR__ . '/..');
assertTest($app->has('graph_rag') === true, "Application container contains 'graph_rag' binding");
assertTest($app->make('graph_rag') instanceof GraphRagManager, "'graph_rag' resolves to GraphRagManager instance");

assertTest(function_exists('graph_rag') && function_exists('graph_rag_ingest') && function_exists('graph_rag_retrieve') && function_exists('graph_rag_context'), "Global GraphRAG helpers exist");
assertTest(graph_rag() instanceof GraphRagManager, "graph_rag() helper returns GraphRagManager instance");

$helperNode = graph_rag_ingest('ent_php82', 'PHP 8.2 is modern typed programming language.', [0.1, 0.2]);
assertTest($helperNode instanceof KnowledgeNode, "graph_rag_ingest() helper ingests node");

$helperHits = graph_rag_retrieve('typed programming language', limit: 1);
assertTest(count($helperHits) >= 1, "graph_rag_retrieve() helper queries graph");

$helperContext = graph_rag_context('PHP 8.2', limit: 1);
assertTest(str_contains($helperContext, 'PHP 8.2'), "graph_rag_context() helper formats context");

// -----------------------------------------------------------------------------
// 12. Database Migration
// -----------------------------------------------------------------------------
$dbConn = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
$pdo = $dbConn->getPdo();

$migration = require __DIR__ . '/../database/migrations/2026_09_28_000074_create_graph_rag_tables.php';
$migration->up($dbConn);

$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('graphrag_nodes', 'graphrag_edges', 'graphrag_documents')");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
assertTest(count($tables) === 3, "Database migration created graphrag_nodes, graphrag_edges, graphrag_documents tables");

// Insert node record into database
$pdo->prepare("
    INSERT INTO graphrag_nodes (id, label, modality, content, vector_embedding, properties, confidence)
    VALUES (?, ?, ?, ?, ?, ?, ?)
")->execute(['node_1', 'Entity', 'text', 'GraphRAG concept', json_encode([0.1, 0.2]), json_encode(['source' => 'wiki']), 1.0]);

$queryStmt = $pdo->prepare("SELECT content FROM graphrag_nodes WHERE id = ?");
$queryStmt->execute(['node_1']);
assertTest($queryStmt->fetchColumn() === 'GraphRAG concept', "GraphRAG node record inserted and queried from database");
$queryStmt->closeCursor();
$queryStmt = null;

// Test rollback
$migration->down($dbConn);
$droppedTables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('graphrag_nodes', 'graphrag_edges', 'graphrag_documents')")->fetchAll(PDO::FETCH_COLUMN);
assertTest(count($droppedTables) === 0, "Database migration down drops all GraphRAG tables");

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n========================================================\n";
echo " Phase 91 Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
