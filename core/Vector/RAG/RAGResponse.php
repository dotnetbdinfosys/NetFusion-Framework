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

class RAGResponse implements JsonSerializable
{
    /**
     * @param string $query Original query
     * @param string $answer Synthesized / grounded response
     * @param RAGContext $context Retrieved context chunks with citations
     * @param float $executionTimeMs Execution time in milliseconds
     * @param array<string, mixed> $metadata Pipeline execution metadata
     */
    public function __construct(
        protected string $query,
        protected string $answer,
        protected RAGContext $context,
        protected float $executionTimeMs = 0.0,
        protected array $metadata = []
    ) {
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    public function getContext(): RAGContext
    {
        return $this->context;
    }

    public function getExecutionTimeMs(): float
    {
        return $this->executionTimeMs;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function jsonSerialize(): array
    {
        return [
            'query' => $this->query,
            'answer' => $this->answer,
            'context' => $this->context->jsonSerialize(),
            'executionTimeMs' => $this->executionTimeMs,
            'metadata' => $this->metadata,
        ];
    }
}
