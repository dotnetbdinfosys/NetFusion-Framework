<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/AI/Contracts/LlmDriverInterface.php
| Module    : AI
| Namespace : NetFusion\Core\AI\Contracts
| Purpose   : Interface for LLM provider drivers (OpenAI, Anthropic, Gemini, Mock).
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
use NetFusion\Core\AI\ChatResponse;

interface LlmDriverInterface
{
    /**
     * Send chat completion request.
     *
     * @param array<ChatMessage|array> $messages
     * @param array<string, mixed>     $options (model, temperature, max_tokens, tools, etc.)
     * @return ChatResponse
     */
    public function chat(array $messages, array $options = []): ChatResponse;

    /**
     * Generate vector embeddings for text input.
     *
     * @param string|array<string> $input
     * @param array<string, mixed> $options
     * @return array<float>|array<array<float>>
     */
    public function embed(string|array $input, array $options = []): array;
}
