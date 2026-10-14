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
use NetFusion\Core\AI\Contracts\MemoryInterface;

class WindowedMemory implements MemoryInterface
{
    /** @var ChatMessage[] */
    protected array $messages = [];
    protected int $windowSize;

    public function __construct(int $windowSize = 10, array $initialMessages = [])
    {
        $this->windowSize = max(1, $windowSize);
        foreach ($initialMessages as $msg) {
            $this->add($msg);
        }
    }

    public function add(string|ChatMessage $roleOrMessage, ?string $content = null, array $metadata = []): void
    {
        if ($roleOrMessage instanceof ChatMessage) {
            $this->messages[] = $roleOrMessage;
        } else {
            $this->messages[] = new ChatMessage(
                role: $roleOrMessage,
                content: $content ?? '',
                metadata: $metadata
            );
        }

        // Keep only system messages and the last windowSize non-system messages
        $this->prune();
    }

    protected function prune(): void
    {
        $systemMessages = [];
        $otherMessages = [];

        foreach ($this->messages as $msg) {
            if ($msg->getRole() === 'system') {
                $systemMessages[] = $msg;
            } else {
                $otherMessages[] = $msg;
            }
        }

        if (count($otherMessages) > $this->windowSize) {
            $otherMessages = array_slice($otherMessages, -$this->windowSize);
        }

        $this->messages = array_merge($systemMessages, $otherMessages);
    }

    /**
     * @return ChatMessage[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getWindowSize(): int
    {
        return $this->windowSize;
    }

    public function setWindowSize(int $size): self
    {
        $this->windowSize = max(1, $size);
        $this->prune();
        return $this;
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function clear(): void
    {
        $this->messages = [];
    }
}
