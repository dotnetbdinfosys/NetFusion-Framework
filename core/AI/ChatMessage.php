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

class ChatMessage implements JsonSerializable
{
    /**
     * @param string $role (system|user|assistant|tool)
     * @param string $content
     * @param string|null $name
     * @param ToolCall[] $toolCalls
     * @param string|null $toolCallId
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        protected string $role,
        protected string $content = '',
        protected ?string $name = null,
        protected array $toolCalls = [],
        protected ?string $toolCallId = null,
        protected array $metadata = []
    ) {
    }

    public static function system(string $content, array $metadata = []): self
    {
        return new self('system', $content, null, [], null, $metadata);
    }

    public static function user(string $content, ?string $name = null, array $metadata = []): self
    {
        return new self('user', $content, $name, [], null, $metadata);
    }

    public static function assistant(string $content = '', array $toolCalls = [], array $metadata = []): self
    {
        return new self('assistant', $content, null, $toolCalls, null, $metadata);
    }

    public static function tool(string $content, string $toolCallId, ?string $name = null, array $metadata = []): self
    {
        return new self('tool', $content, $name, [], $toolCallId, $metadata);
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
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

    public function getToolCallId(): ?string
    {
        return $this->toolCallId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'role' => $this->role,
            'content' => $this->content,
        ];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if (!empty($this->toolCalls)) {
            $data['tool_calls'] = array_map(fn(ToolCall $tc) => $tc->toArray(), $this->toolCalls);
        }

        if ($this->toolCallId !== null) {
            $data['tool_call_id'] = $this->toolCallId;
        }

        if (!empty($this->metadata)) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
