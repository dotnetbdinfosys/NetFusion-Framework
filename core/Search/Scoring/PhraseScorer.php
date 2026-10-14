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

namespace NetFusion\Core\Search\Scoring;

use NetFusion\Core\Search\Indexing\PositionalPosting;

class PhraseScorer
{
    /**
     * Compute exact phrase match bonus score by checking sequential token positions.
     *
     * @param array<int, array<string, PositionalPosting>> $termPostings TermIndex => [DocId => PositionalPosting]
     * @param string $docId
     * @param int $slop Maximum allowed word distance between phrase terms (0 for exact phrase)
     * @return float
     */
    public function scorePhrase(array $termPostings, string $docId, int $slop = 0): float
    {
        if (count($termPostings) < 2) {
            return 0.0;
        }

        // Collect position arrays for this doc across query terms
        $positionLists = [];
        foreach ($termPostings as $termIdx => $postingMap) {
            if (!isset($postingMap[$docId])) {
                return 0.0; // All phrase terms must be present
            }
            $positionLists[] = $postingMap[$docId]->positions;
        }

        $matches = 0;
        $firstPositions = $positionLists[0];

        foreach ($firstPositions as $startPos) {
            $matchedSequence = true;
            $currentPos = $startPos;

            for ($i = 1; $i < count($positionLists); $i++) {
                $foundNext = false;
                foreach ($positionLists[$i] as $candidatePos) {
                    $diff = $candidatePos - $currentPos;
                    if ($diff >= 1 && $diff <= (1 + $slop)) {
                        $currentPos = $candidatePos;
                        $foundNext = true;
                        break;
                    }
                }

                if (!$foundNext) {
                    $matchedSequence = false;
                    break;
                }
            }

            if ($matchedSequence) {
                $matches++;
            }
        }

        return $matches > 0 ? (float)($matches * 2.5) : 0.0;
    }
}
