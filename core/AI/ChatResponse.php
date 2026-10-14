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

namespace NetFusion\Core\AI;

use JsonSerializable;

class ChatResponse implements JsonSerializable
{
    /**
     * @param string $content
     * @param string $role
     * @param ToolCall[] $toolCalls
     * @param array<string, int> $usage ['prompt_tokens' => int, 'completion_tokens' => int, 'total_tokens' => int]
     * @param array<string, mixed> $raw
     * @param string|null $model
     * @param string|null $finishReason
     */
    public function __construct(
        protected string $content = '',
        protected string $role = 'assistant',
        protected array $toolCalls = [],
        protected array $usage = [],
        protected array $raw = [],
        protected ?string $model = null,
        protected ?string $finishReason = null
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * @return ToolCall[]
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }

    /**
     * @return array<string, int>
     */
    public function getUsage(): array
    {
        return $this->usage;
    }

    public function getPromptTokens(): int
    {
        return $this->usage['prompt_tokens'] ?? 0;
    }

    public function getCompletionTokens(): int
    {
        return $this->usage['completion_tokens'] ?? 0;
    }

    public function getTotalTokens(): int
    {
        return $this->usage['total_tokens'] ?? ($this->getPromptTokens() + $this->getCompletionTokens());
    }

    /**
     * @return array<string, mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getFinishReason(): ?string
    {
        return $this->finishReason;
    }

    public function toChatMessage(): ChatMessage
    {
        return new ChatMessage(
            role: $this->role,
            content: $this->content,
            toolCalls: $this->toolCalls
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
            'tool_calls' => array_map(fn(ToolCall $tc) => $tc->toArray(), $this->toolCalls),
            'usage' => $this->usage,
            'model' => $this->model,
            'finish_reason' => $this->finishReason,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
