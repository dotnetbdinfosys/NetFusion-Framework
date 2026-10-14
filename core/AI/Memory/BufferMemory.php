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

class BufferMemory implements MemoryInterface
{
    /** @var ChatMessage[] */
    protected array $messages = [];

    /**
     * @param ChatMessage[] $initialMessages
     */
    public function __construct(array $initialMessages = [])
    {
        foreach ($initialMessages as $msg) {
            $this->add($msg);
        }
    }

    public function add(string|ChatMessage $roleOrMessage, ?string $content = null, array $metadata = []): void
    {
        if ($roleOrMessage instanceof ChatMessage) {
            $this->messages[] = $roleOrMessage;
            return;
        }

        $this->messages[] = new ChatMessage(
            role: $roleOrMessage,
            content: $content ?? '',
            metadata: $metadata
        );
    }

    /**
     * @return ChatMessage[]
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
     * Get recent N messages.
     *
     * @param int $limit
     * @return ChatMessage[]
     */
    public function getRecent(int $limit): array
    {
        return array_slice($this->messages, -$limit);
    }
}
