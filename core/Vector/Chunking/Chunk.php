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

use JsonSerializable;

class Chunk implements JsonSerializable
{
    /**
     * @param string $text Content of the chunk
     * @param int $index 0-based chunk index within source document
     * @param int $startOffset Character start offset in source document
     * @param int $endOffset Character end offset in source document
     * @param int $tokenCount Estimated token count
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        protected string $text,
        protected int $index = 0,
        protected int $startOffset = 0,
        protected int $endOffset = 0,
        protected int $tokenCount = 0,
        protected array $metadata = []
    ) {
        if ($this->tokenCount <= 0) {
            // Estimate tokens (~4 characters per token average)
            $this->tokenCount = (int)ceil(mb_strlen($text, 'UTF-8') / 4.0);
        }
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getStartOffset(): int
    {
        return $this->startOffset;
    }

    public function getEndOffset(): int
    {
        return $this->endOffset;
    }

    public function getTokenCount(): int
    {
        return $this->tokenCount;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function withMetadata(array $metadata): self
    {
        $clone = clone $this;
        $clone->metadata = array_merge($this->metadata, $metadata);
        return $clone;
    }

    public function jsonSerialize(): array
    {
        return [
            'text' => $this->text,
            'index' => $this->index,
            'startOffset' => $this->startOffset,
            'endOffset' => $this->endOffset,
            'tokenCount' => $this->tokenCount,
            'metadata' => $this->metadata,
        ];
    }
}
