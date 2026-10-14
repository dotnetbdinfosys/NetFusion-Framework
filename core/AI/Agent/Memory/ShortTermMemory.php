<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Memory;

class ShortTermMemory
{
    /** @var MemoryMessage[] */
    private array $messages = [];

    public function __construct(
        private readonly int $maxMessages = 50
    ) {
    }

    public function add(MemoryMessage $message): void
    {
        $this->messages[] = $message;

        if (count($this->messages) > $this->maxMessages) {
            // Keep system message if it is the first message
            if (isset($this->messages[0]) && $this->messages[0]->getRole() === 'system') {
                $system = $this->messages[0];
                array_shift($this->messages);
                array_shift($this->messages); // Remove oldest user/assistant message
                array_unshift($this->messages, $system);
            } else {
                array_shift($this->messages);
            }
        }
    }

    public function addSystemMessage(string $content): self
    {
        $this->add(MemoryMessage::system($content));
        return $this;
    }

    public function addUserMessage(string $content, ?string $name = null): self
    {
        $this->add(MemoryMessage::user($content, $name));
        return $this;
    }

    public function addAssistantMessage(string $content, ?array $toolCalls = null): self
    {
        $this->add(MemoryMessage::assistant($content, $toolCalls));
        return $this;
    }

    public function addToolMessage(string $content, string $toolCallId, ?string $name = null): self
    {
        $this->add(MemoryMessage::tool($content, $toolCallId, $name));
        return $this;
    }

    /**
     * @return MemoryMessage[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function clear(): void
    {
        $this->messages = [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toChatHistory(): array
    {
        return array_map(fn(MemoryMessage $m) => $m->toArray(), $this->messages);
    }
}
