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

namespace NetFusion\Core\Vector\Chunking;

use NetFusion\Core\Vector\Contracts\DocumentSplitterInterface;

class RecursiveCharacterSplitter implements DocumentSplitterInterface
{
    /**
     * @var array<string>
     */
    protected array $separators;

    /**
     * @param int $chunkSize Target maximum chunk size in characters
     * @param int $chunkOverlap Overlapping characters between adjacent chunks
     * @param array<string>|null $separators Hierarchical list of separators
     */
    public function __construct(
        protected int $chunkSize = 1000,
        protected int $chunkOverlap = 200,
        ?array $separators = null
    ) {
        $this->separators = $separators ?? ["\n\n", "\n", ". ", "? ", "! ", "; ", " ", ""];
    }

    public function splitText(string $text, array $metadata = []): array
    {
        $rawChunks = $this->splitRecursive($text, $this->separators);
        $mergedChunks = $this->mergeChunks($rawChunks);

        $results = [];
        $currentOffset = 0;
        $textLen = mb_strlen($text, 'UTF-8');

        foreach ($mergedChunks as $index => $chunkText) {
            $chunkLength = mb_strlen($chunkText, 'UTF-8');
            $searchOffset = min($currentOffset, max(0, $textLen - 1));
            $pos = false;

            if ($textLen > 0 && $searchOffset < $textLen) {
                $prefix = mb_substr($chunkText, 0, min(30, $chunkLength), 'UTF-8');
                $pos = mb_strpos($text, $prefix, $searchOffset, 'UTF-8');
                if ($pos === false && $searchOffset > 0) {
                    $pos = mb_strpos($text, $prefix, 0, 'UTF-8');
                }
            }

            if ($pos === false) {
                $pos = min($currentOffset, $textLen);
            }

            $start = (int)$pos;
            $end = min($textLen, $start + $chunkLength);
            $currentOffset = min($textLen, max($start + 1, $end - $this->chunkOverlap));

            $results[] = new Chunk(
                text: $chunkText,
                index: $index,
                startOffset: $start,
                endOffset: $end,
                tokenCount: (int)ceil($chunkLength / 4.0),
                metadata: $metadata
            );
        }

        return $results;
    }

    /**
     * Recursively split text using the first matching separator.
     *
     * @param array<string> $separators
     * @return array<string>
     */
    protected function splitRecursive(string $text, array $separators): array
    {
        $finalChunks = [];
        $separator = end($separators);
        $newSeparators = [];

        foreach ($separators as $i => $s) {
            if ($s === "") {
                $separator = "";
                break;
            }
            if (str_contains($text, $s)) {
                $separator = $s;
                $newSeparators = array_slice($separators, $i + 1);
                break;
            }
        }

        $splits = $this->splitOnSeparator($text, $separator);

        $goodSplits = [];
        foreach ($splits as $s) {
            if (mb_strlen($s, 'UTF-8') < $this->chunkSize) {
                $goodSplits[] = $s;
            } else {
                if (!empty($goodSplits)) {
                    $merged = $this->mergeChunks($goodSplits);
                    $finalChunks = array_merge($finalChunks, $merged);
                    $goodSplits = [];
                }
                if (empty($newSeparators)) {
                    $finalChunks[] = $s;
                } else {
                    $otherChunks = $this->splitRecursive($s, $newSeparators);
                    $finalChunks = array_merge($finalChunks, $otherChunks);
                }
            }
        }

        if (!empty($goodSplits)) {
            $merged = $this->mergeChunks($goodSplits);
            $finalChunks = array_merge($finalChunks, $merged);
        }

        return $finalChunks;
    }

    /**
     * Split text by separator while keeping separator structure.
     *
     * @return array<string>
     */
    protected function splitOnSeparator(string $text, string $separator): array
    {
        if ($separator === "") {
            // Split character by character
            return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
        }

        return explode($separator, $text);
    }

    /**
     * Merge small split pieces together up to chunkSize with chunkOverlap.
     *
     * @param array<string> $splits
     * @return array<string>
     */
    protected function mergeChunks(array $splits): array
    {
        $docs = [];
        $currentDoc = [];
        $total = 0;

        foreach ($splits as $piece) {
            $piece = trim($piece);
            if ($piece === "") {
                continue;
            }

            $len = mb_strlen($piece, 'UTF-8');
            if ($total + $len + (empty($currentDoc) ? 0 : 1) > $this->chunkSize && !empty($currentDoc)) {
                $merged = implode(" ", $currentDoc);
                $docs[] = $merged;

                // Retain overlap for next chunk
                while ($total > $this->chunkOverlap && count($currentDoc) > 1) {
                    $removed = array_shift($currentDoc);
                    $total -= mb_strlen($removed, 'UTF-8') + 1;
                }
            }

            $currentDoc[] = $piece;
            $total += $len + (count($currentDoc) > 1 ? 1 : 0);
        }

        if (!empty($currentDoc)) {
            $docs[] = implode(" ", $currentDoc);
        }

        return $docs;
    }
}
