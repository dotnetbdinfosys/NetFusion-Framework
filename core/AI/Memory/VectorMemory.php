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

namespace NetFusion\Core\AI\Memory;

use NetFusion\Core\AI\ChatMessage;
use NetFusion\Core\AI\Contracts\LlmDriverInterface;
use NetFusion\Core\AI\Contracts\MemoryInterface;
use NetFusion\Core\AI\Contracts\VectorStoreInterface;

class VectorMemory implements MemoryInterface
{
    /** @var ChatMessage[] */
    protected array $messages = [];
    protected string $collection;

    public function __construct(
        protected VectorStoreInterface $vectorStore,
        protected LlmDriverInterface $driver,
        string $collection = 'agent_memory'
    ) {
        $this->collection = $collection;
    }

    public function add(string|ChatMessage $roleOrMessage, ?string $content = null, array $metadata = []): void
    {
        $message = $roleOrMessage instanceof ChatMessage
            ? $roleOrMessage
            : new ChatMessage($roleOrMessage, $content ?? '', metadata: $metadata);

        $this->messages[] = $message;

        // Index in vector store if content is not empty
        $text = $message->getContent();
        if (!empty(trim($text))) {
            try {
                $vector = $this->driver->embed($text);
                if (is_array($vector) && !empty($vector) && is_numeric($vector[0] ?? null)) {
                    $id = uniqid('mem_');
                    $this->vectorStore->add(
                        id: $id,
                        vector: $vector,
                        content: $text,
                        metadata: array_merge(['role' => $message->getRole()], $message->getMetadata()),
                        collection: $this->collection
                    );
                }
            } catch (\Throwable) {
                // Silently ignore embedding failure in vector memory fallback
            }
        }
    }

    /**
     * Retrieve semantically relevant past messages or memories for a query.
     *
     * @param string $query
     * @param int $limit
     * @param float $minScore
     * @return array<array{id: string, score: float, content: string, metadata: array}>
     */
    public function searchRelevant(string $query, int $limit = 3, float $minScore = 0.5): array
    {
        try {
            $queryVector = $this->driver->embed($query);
            if (is_array($queryVector) && !empty($queryVector) && is_numeric($queryVector[0] ?? null)) {
                return $this->vectorStore->search($queryVector, $limit, $minScore, $this->collection);
            }
        } catch (\Throwable) {
            return [];
        }

        return [];
    }

    /**
     * @return ChatMessage[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function clear(): void
    {
        $this->messages = [];
        $this->vectorStore->clear($this->collection);
    }

    public function getVectorStore(): VectorStoreInterface
    {
        return $this->vectorStore;
    }
}
