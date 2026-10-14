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

namespace NetFusion\Core\AI\Drivers;

use NetFusion\Core\AI\ChatMessage;
use NetFusion\Core\AI\ChatResponse;
use NetFusion\Core\AI\Contracts\LlmDriverInterface;
use NetFusion\Core\AI\ToolCall;

class MockAiDriver implements LlmDriverInterface
{
    /** @var array<ChatResponse|callable|string> */
    protected array $responseQueue = [];

    /** @var array<array{messages: array, options: array}> */
    protected array $history = [];

    /** @var callable|null */
    protected $defaultHandler = null;

    protected int $embeddingDimensions = 16;

    public function __construct(array $responses = [])
    {
        $this->responseQueue = $responses;
    }

    /**
     * Add a mock response or handler to the response queue.
     */
    public function queueResponse(ChatResponse|callable|string $response): self
    {
        $this->responseQueue[] = $response;
        return $this;
    }

    public function setDefaultHandler(?callable $handler): self
    {
        $this->defaultHandler = $handler;
        return $this;
    }

    public function setEmbeddingDimensions(int $dim): self
    {
        $this->embeddingDimensions = $dim;
        return $this;
    }

    public function chat(array $messages, array $options = []): ChatResponse
    {
        $this->history[] = [
            'messages' => $messages,
            'options' => $options,
        ];

        // 1. If response queue is not empty, shift the next response
        if (!empty($this->responseQueue)) {
            $next = array_shift($this->responseQueue);

            if ($next instanceof ChatResponse) {
                return $next;
            }

            if (is_callable($next)) {
                $result = $next($messages, $options);
                if ($result instanceof ChatResponse) {
                    return $result;
                }
                return new ChatResponse(content: (string)$result, model: $options['model'] ?? 'mock-model');
            }

            return new ChatResponse(content: (string)$next, model: $options['model'] ?? 'mock-model');
        }

        // 2. If default handler is set
        if ($this->defaultHandler !== null) {
            $result = ($this->defaultHandler)($messages, $options);
            if ($result instanceof ChatResponse) {
                return $result;
            }
            return new ChatResponse(content: (string)$result, model: $options['model'] ?? 'mock-model');
        }

        // 3. Fallback deterministic response
        $lastMsg = end($messages);
        $lastContent = $lastMsg instanceof ChatMessage ? $lastMsg->getContent() : ($lastMsg['content'] ?? '');

        return new ChatResponse(
            content: "Mock response to: " . $lastContent,
            role: 'assistant',
            toolCalls: [],
            usage: ['prompt_tokens' => 10, 'completion_tokens' => 15, 'total_tokens' => 25],
            model: $options['model'] ?? 'mock-ai-v1'
        );
    }

    public function embed(string|array $input, array $options = []): array
    {
        $dim = $options['dimensions'] ?? $this->embeddingDimensions;
        
        if (is_array($input)) {
            return array_map(fn($text) => $this->generateVector((string)$text, $dim), $input);
        }

        return $this->generateVector($input, $dim);
    }

    /**
     * @return array<array{messages: array, options: array}>
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    public function clearHistory(): void
    {
        $this->history = [];
    }

    /**
     * Deterministic vector generator for a given text.
     *
     * @return array<float>
     */
    protected function generateVector(string $text, int $dim): array
    {
        $vector = [];
        $hash = md5($text);
        
        for ($i = 0; $i < $dim; $i++) {
            $sub = substr($hash, ($i * 2) % 30, 2);
            $val = (hexdec($sub) / 255.0) * 2.0 - 1.0; // [-1.0, 1.0]
            $vector[] = round($val, 4);
        }

        // Normalize vector
        $norm = sqrt(array_sum(array_map(fn($v) => $v * $v, $vector)));
        if ($norm > 0) {
            $vector = array_map(fn($v) => round($v / $norm, 4), $vector);
        }

        return $vector;
    }
}
