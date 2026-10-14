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

class MarkdownHeaderSplitter implements DocumentSplitterInterface
{
    /**
     * @var array<string, string> Heading markdown prefix => metadata key name
     */
    protected array $headersToSplitOn;

    /**
     * @param array<string, string>|null $headersToSplitOn e.g. ['#' => 'Header 1', '##' => 'Header 2', '###' => 'Header 3']
     */
    public function __construct(?array $headersToSplitOn = null)
    {
        $this->headersToSplitOn = $headersToSplitOn ?? [
            '#' => 'header_1',
            '##' => 'header_2',
            '###' => 'header_3',
        ];
    }

    public function splitText(string $text, array $metadata = []): array
    {
        $lines = explode("\n", $text);
        $chunks = [];
        $currentChunkLines = [];
        $currentHeaders = [];
        $index = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $matchedHeaderLevel = null;
            $matchedHeaderText = null;

            foreach ($this->headersToSplitOn as $prefix => $metaKey) {
                if (str_starts_with($trimmed, $prefix . ' ')) {
                    $matchedHeaderLevel = $metaKey;
                    $matchedHeaderText = trim(substr($trimmed, strlen($prefix) + 1));
                    break;
                }
            }

            if ($matchedHeaderLevel !== null) {
                // Flush current chunk if non-empty
                if (!empty($currentChunkLines)) {
                    $chunkText = trim(implode("\n", $currentChunkLines));
                    if ($chunkText !== '') {
                        $chunks[] = new Chunk(
                            text: $chunkText,
                            index: $index++,
                            metadata: array_merge($metadata, $currentHeaders)
                        );
                    }
                    $currentChunkLines = [];
                }

                $currentHeaders[$matchedHeaderLevel] = $matchedHeaderText;
            }

            $currentChunkLines[] = $line;
        }

        // Flush last chunk
        if (!empty($currentChunkLines)) {
            $chunkText = trim(implode("\n", $currentChunkLines));
            if ($chunkText !== '') {
                $chunks[] = new Chunk(
                    text: $chunkText,
                    index: $index++,
                    metadata: array_merge($metadata, $currentHeaders)
                );
            }
        }

        return $chunks;
    }
}
