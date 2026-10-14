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

use NetFusion\Core\AI\AiException;
use NetFusion\Core\AI\ChatMessage;
use NetFusion\Core\AI\ChatResponse;
use NetFusion\Core\AI\Contracts\LlmDriverInterface;
use NetFusion\Core\AI\ToolCall;

class AnthropicDriver implements LlmDriverInterface
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $defaultModel;
    protected string $apiVersion;
    protected int $timeout;

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? (string)getenv('ANTHROPIC_API_KEY');
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.anthropic.com/v1', '/');
        $this->defaultModel = $config['model'] ?? 'claude-3-5-sonnet-20241022';
        $this->apiVersion = $config['version'] ?? '2023-06-01';
        $this->timeout = (int)($config['timeout'] ?? 30);
    }

    public function chat(array $messages, array $options = []): ChatResponse
    {
        $systemPrompt = $options['system'] ?? '';
        $formattedMessages = [];

        foreach ($messages as $msg) {
            $role = $msg instanceof ChatMessage ? $msg->getRole() : ($msg['role'] ?? 'user');
            $content = $msg instanceof ChatMessage ? $msg->getContent() : ($msg['content'] ?? '');

            if ($role === 'system') {
                $systemPrompt .= ($systemPrompt !== '' ? "\n\n" : '') . $content;
                continue;
            }

            if ($msg instanceof ChatMessage && $msg->getToolCalls()) {
                $contentBlocks = [];
                if ($content !== '') {
                    $contentBlocks[] = ['type' => 'text', 'text' => $content];
                }
                foreach ($msg->getToolCalls() as $tc) {
                    $contentBlocks[] = [
                        'type' => 'tool_use',
                        'id' => $tc->getId(),
                        'name' => $tc->getName(),
                        'input' => $tc->getArguments(),
                    ];
                }
                $formattedMessages[] = ['role' => 'assistant', 'content' => $contentBlocks];
                continue;
            }

            if ($role === 'tool') {
                $toolCallId = $msg instanceof ChatMessage ? $msg->getToolCallId() : ($msg['tool_call_id'] ?? '');
                $formattedMessages[] = [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'tool_result',
                            'tool_use_id' => $toolCallId,
                            'content' => $content,
                        ],
                    ],
                ];
                continue;
            }

            $formattedMessages[] = [
                'role' => $role === 'assistant' ? 'assistant' : 'user',
                'content' => $content,
            ];
        }

        $payload = [
            'model' => $options['model'] ?? $this->defaultModel,
            'messages' => $formattedMessages,
            'max_tokens' => (int)($options['max_tokens'] ?? 2048),
        ];

        if ($systemPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = (float)$options['temperature'];
        }

        // Handle tools
        if (!empty($options['tools'])) {
            $anthropicTools = [];
            foreach ($options['tools'] as $tool) {
                if (isset($tool['function'])) {
                    $anthropicTools[] = [
                        'name' => $tool['function']['name'] ?? '',
                        'description' => $tool['function']['description'] ?? '',
                        'input_schema' => $tool['function']['parameters'] ?? ['type' => 'object'],
                    ];
                } elseif (isset($tool['name'])) {
                    $anthropicTools[] = [
                        'name' => $tool['name'],
                        'description' => $tool['description'] ?? '',
                        'input_schema' => $tool['parameters'] ?? $tool['input_schema'] ?? ['type' => 'object'],
                    ];
                }
            }
            $payload['tools'] = $anthropicTools;
        }

        $response = $this->sendRequest('/messages', $payload);

        $content = '';
        $toolCalls = [];
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $content .= $block['text'] ?? '';
            } elseif (($block['type'] ?? '') === 'tool_use') {
                $toolCalls[] = new ToolCall(
                    id: $block['id'] ?? uniqid('call_'),
                    name: $block['name'] ?? '',
                    arguments: (array)($block['input'] ?? [])
                );
            }
        }

        $usage = [
            'prompt_tokens' => $response['usage']['input_tokens'] ?? 0,
            'completion_tokens' => $response['usage']['output_tokens'] ?? 0,
            'total_tokens' => ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0),
        ];

        return new ChatResponse(
            content: $content,
            role: 'assistant',
            toolCalls: $toolCalls,
            usage: $usage,
            raw: $response,
            model: $response['model'] ?? ($payload['model']),
            finishReason: $response['stop_reason'] ?? null
        );
    }

    public function embed(string|array $input, array $options = []): array
    {
        throw new AiException("Anthropic API does not natively support embeddings. Use OpenAI or Gemini driver for vector embeddings.");
    }

    /**
     * @param string $endpoint
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function sendRequest(string $endpoint, array $payload): array
    {
        $url = $this->baseUrl . $endpoint;
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $headers = [
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: ' . $this->apiVersion,
            'content-type: application/json',
            'accept: application/json',
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $result = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($result === false) {
                throw new AiException("Anthropic API Request failed: " . $error);
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers),
                    'content' => $jsonPayload,
                    'timeout' => $this->timeout,
                    'ignore_errors' => true,
                ],
            ]);

            $result = @file_get_contents($url, false, $context);
            if ($result === false) {
                throw new AiException("Failed to open connection to Anthropic API: " . $url);
            }

            $httpCode = 200;
            if (isset($http_response_header[0])) {
                preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
                $httpCode = isset($matches[1]) ? (int)$matches[1] : 200;
            }
        }

        $decoded = json_decode($result, true);
        if ($httpCode >= 400 || !is_array($decoded)) {
            $errMsg = $decoded['error']['message'] ?? ("HTTP " . $httpCode . ": " . substr($result, 0, 200));
            throw new AiException("Anthropic API Error: " . $errMsg);
        }

        return $decoded;
    }
}
