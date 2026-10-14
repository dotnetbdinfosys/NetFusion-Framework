<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
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

namespace NetFusion\Core\GraphRag\Prompt;

use NetFusion\Core\GraphRag\Entity\KnowledgeNode;
use NetFusion\Core\GraphRag\Graph\VectorKnowledgeGraph;

/**
 * Formats multi-modal retrieved knowledge chunks and relational graph edges into grounded prompt context for LLMs.
 */
class ContextSynthesizer
{
    /**
     * Synthesize retrieved items and related graph topology into a grounded context string.
     *
     * @param array<int, array{node: KnowledgeNode, rerankScore?: float, score?: float, rank?: int}> $retrievedItems
     * @param VectorKnowledgeGraph $graph
     * @param string $query
     */
    public static function synthesize(array $retrievedItems, VectorKnowledgeGraph $graph, string $query = ''): string
    {
        if (empty($retrievedItems)) {
            return "No relevant context found in knowledge graph.";
        }

        $lines = [];
        $lines[] = "=== GROUNDED KNOWLEDGE GRAPH CONTEXT ===";
        if ($query !== '') {
            $lines[] = "Query: {$query}";
        }
        $lines[] = "";

        // 1. Relational Graph Triples
        $nodeIds = [];
        foreach ($retrievedItems as $item) {
            $nodeIds[] = $item['node']->getId();
        }

        $relevantEdges = [];
        foreach ($nodeIds as $nid) {
            foreach ($graph->getEdges($nid) as $edge) {
                $edgeKey = $edge->getSourceId() . '->' . $edge->getRelationship() . '->' . $edge->getTargetId();
                $relevantEdges[$edgeKey] = $edge;
            }
        }

        if (!empty($relevantEdges)) {
            $lines[] = "[Graph Entity Relationships & Topology]";
            foreach ($relevantEdges as $edge) {
                $lines[] = sprintf(
                    "- (%s) -[%s (w: %.2f)]-> (%s)",
                    $edge->getSourceId(),
                    $edge->getRelationship(),
                    $edge->getWeight(),
                    $edge->getTargetId()
                );
            }
            $lines[] = "";
        }

        // 2. Multi-Modal Document Snippets & Chunks
        $lines[] = "[Verified Knowledge Excerpts]";
        $index = 1;
        foreach ($retrievedItems as $item) {
            $node = $item['node'];
            $score = $item['rerankScore'] ?? $item['score'] ?? 0.0;
            $modality = $node->getModality()->value;

            $lines[] = sprintf(
                "[%d] [%s] ID: %s (Relevance: %.4f)",
                $index,
                strtoupper($modality),
                $node->getId(),
                $score
            );

            if ($node->getContent() !== '') {
                $lines[] = "Content: " . $node->getContent();
            }

            if (!empty($node->getProperties())) {
                $propsStr = json_encode($node->getProperties(), JSON_UNESCAPED_SLASHES);
                $lines[] = "Metadata: {$propsStr}";
            }

            $lines[] = "";
            $index++;
        }

        return implode("\n", $lines);
    }
}
