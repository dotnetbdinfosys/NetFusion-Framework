<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/AI/Contracts/MemoryInterface.php
| Module    : AI
| Namespace : NetFusion\Core\AI\Contracts
| Purpose   : Contract for conversation history and contextual memory storage.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\AI\Contracts;

use NetFusion\Core\AI\ChatMessage;

interface MemoryInterface
{
    /**
     * Add a message to memory.
     *
     * @param string|ChatMessage $roleOrMessage Role or ChatMessage
     * @param string|null        $content
     * @param array<string, mixed> $metadata
     */
    public function add(string|ChatMessage $roleOrMessage, ?string $content = null, array $metadata = []): void;

    /**
     * Retrieve all messages stored in memory formatted as ChatMessage instances.
     *
     * @return array<ChatMessage>
     */
    public function getMessages(): array;

    /**
     * Clear all messages from memory.
     */
    public function clear(): void;
}
