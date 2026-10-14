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

class TokenAwareSplitter implements DocumentSplitterInterface
{
    /**
     * @param int $maxTokens Maximum tokens per chunk (default 256)
     * @param int $tokenOverlap Overlapping tokens between consecutive chunks (default 32)
     */
    public function __construct(
        protected int $maxTokens = 256,
        protected int $tokenOverlap = 32
    ) {
    }

    public function splitText(string $text, array $metadata = []): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (empty($words)) {
            return [];
        }

        // We estimate 1 word ≈ 1.33 tokens
        $wordsPerChunk = max(1, (int)floor($this->maxTokens / 1.33));
        $wordOverlap = min($wordsPerChunk - 1, (int)floor($this->tokenOverlap / 1.33));
        $step = max(1, $wordsPerChunk - $wordOverlap);

        $totalWords = count($words);
        $chunks = [];
        $index = 0;

        for ($i = 0; $i < $totalWords; $i += $step) {
            $slice = array_slice($words, $i, $wordsPerChunk);
            if (empty($slice)) {
                break;
            }

            $chunkText = implode(' ', $slice);
            $tokenCount = (int)ceil(count($slice) * 1.33);

            $chunks[] = new Chunk(
                text: $chunkText,
                index: $index++,
                tokenCount: $tokenCount,
                metadata: $metadata
            );

            if ($i + $wordsPerChunk >= $totalWords) {
                break;
            }
        }

        return $chunks;
    }
}
