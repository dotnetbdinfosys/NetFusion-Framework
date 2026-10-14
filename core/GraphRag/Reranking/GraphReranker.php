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

namespace NetFusion\Core\GraphRag\Reranking;

use NetFusion\Core\GraphRag\Entity\KnowledgeNode;
use NetFusion\Core\GraphRag\Graph\VectorKnowledgeGraph;
use NetFusion\Core\GraphRag\Index\VectorSimilarity;

/**
 * Dynamic Graph Reranker factoring in semantic similarity, graph centrality, and diversity (MMR).
 */
class GraphReranker
{
    public function __construct(
        protected VectorKnowledgeGraph $graph,
        protected float $centralityBonus = 0.15
    ) {
    }

    /**
     * Rerank retrieved candidates balancing relevance score, graph centrality, and novelty.
     *
     * @param array<int, array{node: KnowledgeNode, score: float, rank?: int, matchTypes?: array<int, string>}> $candidates
     * @param int $limit
     * @param float $diversityLambda Weight between relevance (1.0) and diversity (0.0)
     * @return array<int, array{node: KnowledgeNode, rerankScore: float, originalScore: float, rank: int}>
     */
    public function rerank(array $candidates, int $limit = 5, float $diversityLambda = 0.8): array
    {
        if (empty($candidates)) {
            return [];
        }

        // Compute augmented scores incorporating graph topology centrality
        $scored = [];
        foreach ($candidates as $cand) {
            $node = $cand['node'];
            $baseScore = (float) $cand['score'];
            $degree = $this->graph->getNodeDegree($node->getId());
            $centrality = min(1.0, $degree / 4.0);

            $augmented = $baseScore + ($centrality * $this->centralityBonus);
            $scored[] = [
                'node'          => $node,
                'augmented'     => $augmented,
                'originalScore' => $baseScore,
            ];
        }

        // Apply Maximal Marginal Relevance (MMR) selection
        $selected = [];
        $remaining = $scored;

        while (count($selected) < $limit && !empty($remaining)) {
            $bestIdx = 0;
            $bestMmr = -INF;

            foreach ($remaining as $idx => $candidate) {
                $relevance = $candidate['augmented'];
                $maxRedundancy = 0.0;

                if (!empty($selected) && $candidate['node']->hasVector()) {
                    foreach ($selected as $sel) {
                        if ($sel['node']->hasVector()) {
                            try {
                                $sim = VectorSimilarity::cosine($candidate['node']->getVector(), $sel['node']->getVector());
                                if ($sim > $maxRedundancy) {
                                    $maxRedundancy = $sim;
                                }
                            } catch (\Throwable) {
                                // Ignore dimension mismatch
                            }
                        }
                    }
                }

                // MMR formula: lambda * relevance - (1 - lambda) * max_similarity
                $mmr = ($diversityLambda * $relevance) - ((1.0 - $diversityLambda) * $maxRedundancy);

                if ($mmr > $bestMmr) {
                    $bestMmr = $mmr;
                    $bestIdx = $idx;
                }
            }

            $chosen = $remaining[$bestIdx];
            $selected[] = [
                'node'          => $chosen['node'],
                'rerankScore'   => round($bestMmr, 6),
                'originalScore' => $chosen['originalScore'],
                'rank'          => count($selected) + 1,
            ];

            array_splice($remaining, $bestIdx, 1);
        }

        return $selected;
    }
}
