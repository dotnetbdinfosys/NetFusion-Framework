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

use NetFusion\Core\Vector\Contracts\RAGPipelineInterface;
use NetFusion\Core\Vector\VectorEngineManager;
use NetFusion\Core\Vector\Exceptions\CollectionNotFoundException;

class RAGPipeline implements RAGPipelineInterface
{
    /**
     * @var callable|null
     */
    protected $llmGenerator = null;

    /**
     * @param VectorEngineManager $manager
     * @param array<string, mixed> $defaultOptions
     */
    public function __construct(
        protected VectorEngineManager $manager,
        protected array $defaultOptions = [
            'top_k' => 3,
            'type' => 'hybrid',
            'rerank' => true,
            'min_score' => 0.0,
        ]
    ) {
    }

    /**
     * Set a custom LLM generation callback: fn(string $prompt, RAGContext $context): string
     */
    public function setLlmGenerator(callable $generator): void
    {
        $this->llmGenerator = $generator;
    }

    public function query(string $query, string $collectionName, array $options = []): RAGResponse
    {
        $startTime = microtime(true);
        $opts = array_merge($this->defaultOptions, $options);
        $topK = (int)($opts['top_k'] ?? 3);

        $collection = $this->manager->collection($collectionName);
        if ($collection === null) {
            throw new CollectionNotFoundException($collectionName);
        }

        // 1. Retrieve most relevant context chunks
        $searchResults = $collection->search(
            query: $query,
            k: $topK,
            options: [
                'type' => $opts['type'] ?? 'hybrid',
                'filter' => $opts['filter'] ?? [],
                'rerank' => $opts['rerank'] ?? true,
                'rrf_k' => $opts['rrf_k'] ?? 60,
            ]
        );

        $minScore = (float)($opts['min_score'] ?? 0.0);
        $filteredResults = array_filter($searchResults, fn($r) => (float)$r['score'] >= $minScore);

        $context = new RAGContext(array_values($filteredResults));

        // 2. Synthesize Answer
        $answer = $this->generateAnswer($query, $context);

        $elapsedMs = round((microtime(true) - $startTime) * 1000.0, 2);

        return new RAGResponse(
            query: $query,
            answer: $answer,
            context: $context,
            executionTimeMs: $elapsedMs,
            metadata: [
                'collection' => $collectionName,
                'retrieved_count' => $context->count(),
                'total_tokens' => $context->getTotalTokens(),
            ]
        );
    }

    /**
     * Generate answer using configured LLM generator or local grounded extractor.
     */
    protected function generateAnswer(string $query, RAGContext $context): string
    {
        if ($this->llmGenerator !== null) {
            $prompt = $this->buildPrompt($query, $context);
            return (string)call_user_func($this->llmGenerator, $prompt, $context);
        }

        // Local grounded answer extraction
        if ($context->count() === 0) {
            return "No relevant information found in the knowledge base to answer: \"{$query}\"";
        }

        $chunks = $context->getChunks();
        $groundedPoints = [];

        foreach ($chunks as $idx => $chunk) {
            $sourceNum = $idx + 1;
            $text = trim($chunk['content'] ?? '');
            // Split into sentences
            $sentences = preg_split('/(?<=[.?!])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
            $leadSentence = $sentences[0] ?? $text;

            $groundedPoints[] = "- {$leadSentence} [Source #{$sourceNum}]";
        }

        return "Based on the retrieved knowledge base:\n\n" . implode("\n", $groundedPoints);
    }

    /**
     * Build standard RAG prompt.
     */
    public function buildPrompt(string $query, RAGContext $context): string
    {
        $contextBlock = $context->formatPromptContext();

        return <<<PROMPT
You are a reliable AI assistant. Answer the user's question accurately using ONLY the provided context. If the answer cannot be deduced from the context, state that you do not know.

{$contextBlock}

Question: {$query}
Answer:
PROMPT;
    }
}
