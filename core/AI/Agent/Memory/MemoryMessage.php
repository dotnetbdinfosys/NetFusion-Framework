<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Memory;

class MemoryMessage
{
    public function __construct(
        public readonly string $role,
        public readonly string $content,
        public readonly ?string $name = null,
        public readonly ?array $toolCalls = null,
        public readonly ?string $toolCallId = null,
        public readonly float $timestamp = 0.0
    ) {
    }

    public static function system(string $content): self
    {
        return new self('system', $content, timestamp: microtime(true));
    }

    public static function user(string $content, ?string $name = null): self
    {
        return new self('user', $content, name: $name, timestamp: microtime(true));
    }

    public static function assistant(string $content, ?array $toolCalls = null): self
    {
        return new self('assistant', $content, toolCalls: $toolCalls, timestamp: microtime(true));
    }

    public static function tool(string $content, string $toolCallId, ?string $name = null): self
    {
        return new self('tool', $content, name: $name, toolCallId: $toolCallId, timestamp: microtime(true));
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getToolCalls(): ?array
    {
        return $this->toolCalls;
    }

    public function getToolCallId(): ?string
    {
        return $this->toolCallId;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function toArray(): array
    {
        $arr = [
            'role' => $this->role,
            'content' => $this->content,
        ];

        if ($this->name !== null) {
            $arr['name'] = $this->name;
        }
        if ($this->toolCalls !== null) {
            $arr['tool_calls'] = $this->toolCalls;
        }
        if ($this->toolCallId !== null) {
            $arr['tool_call_id'] = $this->toolCallId;
        }

        return $arr;
    }
}
