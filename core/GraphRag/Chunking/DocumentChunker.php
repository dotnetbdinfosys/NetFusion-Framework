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

namespace NetFusion\Core\GraphRag\Chunking;

/**
 * Splits multi-modal documents into overlapping semantic chunks and extracts entity anchors.
 */
class DocumentChunker
{
    /**
     * Chunk text into overlapping window segments based on character/token count and sentence boundaries.
     *
     * @param string $text
     * @param int $chunkSize Target character length per chunk
     * @param int $overlap Overlap characters between consecutive chunks
     * @return array<int, array{id: string, text: string, offset: int, length: int}>
     */
    public static function chunkText(string $text, int $chunkSize = 500, int $overlap = 100): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $length = mb_strlen($text);
        if ($length <= $chunkSize) {
            return [
                [
                    'id'     => 'chunk_0',
                    'text'   => $text,
                    'offset' => 0,
                    'length' => $length,
                ],
            ];
        }

        $chunks = [];
        $start = 0;
        $chunkIndex = 0;

        while ($start < $length) {
            $end = min($start + $chunkSize, $length);

            // Attempt to break at end of sentence or whitespace if not at EOF
            if ($end < $length) {
                $sub = mb_substr($text, $start, $chunkSize);
                $lastSentencePunct = max(
                    (int) mb_strrpos($sub, '. '),
                    (int) mb_strrpos($sub, ".\n"),
                    (int) mb_strrpos($sub, '! '),
                    (int) mb_strrpos($sub, '? ')
                );

                if ($lastSentencePunct > (int) ($chunkSize * 0.4)) {
                    $end = $start + $lastSentencePunct + 1;
                } else {
                    $lastSpace = (int) mb_strrpos($sub, ' ');
                    if ($lastSpace > (int) ($chunkSize * 0.4)) {
                        $end = $start + $lastSpace;
                    }
                }
            }

            $chunkContent = trim(mb_substr($text, $start, $end - $start));
            if ($chunkContent !== '') {
                $chunks[] = [
                    'id'     => 'chunk_' . $chunkIndex,
                    'text'   => $chunkContent,
                    'offset' => $start,
                    'length' => mb_strlen($chunkContent),
                ];
                $chunkIndex++;
            }

            if ($end >= $length) {
                break;
            }

            // Move start pointer forward taking overlap into account
            $step = max(1, ($end - $start) - $overlap);
            $start += $step;
        }

        return $chunks;
    }

    /**
     * Extract significant candidate entities and keywords from text.
     *
     * @return array<int, string>
     */
    public static function extractEntityKeywords(string $text): array
    {
        // Extract capitalized words or acronyms (e.g., "Google", "DeepMind", "NetFusion", "API")
        preg_match_all('/\b[A-Z][a-zA-Z0-9_\-]{2,}\b/', $text, $matches);
        $entities = array_unique($matches[0] ?? []);

        // Filter common English stopwords that start with capitals
        $stopwords = ['The', 'This', 'That', 'These', 'Those', 'There', 'When', 'Where', 'Which', 'What', 'With', 'From', 'Into', 'About'];
        $filtered = array_values(array_filter($entities, fn($e) => !in_array($e, $stopwords, true)));

        return $filtered;
    }
}
