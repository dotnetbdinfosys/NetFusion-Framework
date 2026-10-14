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

namespace NetFusion\Core\Vector\RAG;

use JsonSerializable;

class RAGContext implements JsonSerializable
{
    /**
     * @param array<array{id: string, score: float, content: string, metadata: array<string, mixed>}> $chunks
     * @param int $totalTokens
     */
    public function __construct(
        protected array $chunks = [],
        protected int $totalTokens = 0
    ) {
        if ($this->totalTokens <= 0) {
            $totalChars = 0;
            foreach ($this->chunks as $chunk) {
                $totalChars += mb_strlen($chunk['content'] ?? '', 'UTF-8');
            }
            $this->totalTokens = (int)ceil($totalChars / 4.0);
        }
    }

    /**
     * @return array<array{id: string, score: float, content: string, metadata: array<string, mixed>}>
     */
    public function getChunks(): array
    {
        return $this->chunks;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    public function count(): int
    {
        return count($this->chunks);
    }

    /**
     * Format retrieved contexts into structured prompt block with citations.
     */
    public function formatPromptContext(): string
    {
        if (empty($this->chunks)) {
            return "No relevant context found.";
        }

        $lines = ["--- BEGIN RELEVANT CONTEXT ---"];
        foreach ($this->chunks as $i => $chunk) {
            $citationNum = $i + 1;
            $id = $chunk['id'];
            $content = trim($chunk['content'] ?? '');
            $score = round((float)($chunk['score'] ?? 0.0), 4);
            $meta = json_encode($chunk['metadata'] ?? [], JSON_UNESCAPED_SLASHES);

            $lines[] = "[Source #{$citationNum} | ID: {$id} | Relevance: {$score}]";
            $lines[] = $content;
            $lines[] = "";
        }
        $lines[] = "--- END RELEVANT CONTEXT ---";

        return implode("\n", $lines);
    }

    public function jsonSerialize(): array
    {
        return [
            'chunks' => $this->chunks,
            'totalTokens' => $this->totalTokens,
            'count' => count($this->chunks),
        ];
    }
}
