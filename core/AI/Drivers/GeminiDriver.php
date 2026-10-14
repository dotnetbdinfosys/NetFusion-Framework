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

class GeminiDriver implements LlmDriverInterface
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $defaultModel;
    protected string $defaultEmbeddingModel;
    protected int $timeout;

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? (string)getenv('GEMINI_API_KEY');
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta', '/');
        $this->defaultModel = $config['model'] ?? 'gemini-1.5-flash';
        $this->defaultEmbeddingModel = $config['embedding_model'] ?? 'text-embedding-004';
        $this->timeout = (int)($config['timeout'] ?? 30);
    }

    public function chat(array $messages, array $options = []): ChatResponse
    {
        $contents = [];
        $systemInstruction = null;

        foreach ($messages as $msg) {
            $role = $msg instanceof ChatMessage ? $msg->getRole() : ($msg['role'] ?? 'user');
            $content = $msg instanceof ChatMessage ? $msg->getContent() : ($msg['content'] ?? '');

            if ($role === 'system') {
                $systemInstruction = [
                    'parts' => [['text' => $content]],
                ];
                continue;
            }

            if ($role === 'tool') {
                $toolCallId = $msg instanceof ChatMessage ? $msg->getToolCallId() : ($msg['tool_call_id'] ?? 'tool');
                $toolName = $msg instanceof ChatMessage ? ($msg->getName() ?? 'tool') : ($msg['name'] ?? 'tool');
                $contents[] = [
                    'role' => 'function',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name' => $toolName,
                                'response' => ['result' => $content],
                            ],
                        ],
                    ],
                ];
                continue;
            }

            $geminiRole = $role === 'assistant' ? 'model' : 'user';
            $parts = [];

            if ($content !== '') {
                $parts[] = ['text' => $content];
            }

            if ($msg instanceof ChatMessage && $msg->getToolCalls()) {
                foreach ($msg->getToolCalls() as $tc) {
                    $parts[] = [
                        'functionCall' => [
                            'name' => $tc->getName(),
                            'args' => $tc->getArguments(),
                        ],
                    ];
                }
            }

            if (!empty($parts)) {
                $contents[] = [
                    'role' => $geminiRole,
                    'parts' => $parts,
                ];
            }
        }

        $payload = [
            'contents' => $contents,
        ];

        if ($systemInstruction !== null) {
            $payload['systemInstruction'] = $systemInstruction;
        }

        $genConfig = [];
        if (isset($options['temperature'])) {
            $genConfig['temperature'] = (float)$options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            $genConfig['maxOutputTokens'] = (int)$options['max_tokens'];
        }
        if (!empty($genConfig)) {
            $payload['generationConfig'] = $genConfig;
        }

        // Tools
        if (!empty($options['tools'])) {
            $funcDecls = [];
            foreach ($options['tools'] as $t) {
                if (isset($t['function'])) {
                    $funcDecls[] = [
                        'name' => $t['function']['name'] ?? '',
                        'description' => $t['function']['description'] ?? '',
                        'parameters' => $t['function']['parameters'] ?? null,
                    ];
                } elseif (isset($t['name'])) {
                    $funcDecls[] = [
                        'name' => $t['name'],
                        'description' => $t['description'] ?? '',
                        'parameters' => $t['parameters'] ?? null,
                    ];
                }
            }
            $payload['tools'] = [['functionDeclarations' => $funcDecls]];
        }

        $model = $options['model'] ?? $this->defaultModel;
        $endpoint = "/models/{$model}:generateContent?key={$this->apiKey}";

        $response = $this->sendRequest($endpoint, $payload);

        $candidate = $response['candidates'][0] ?? [];
        $parts = $candidate['content']['parts'] ?? [];

        $content = '';
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $content .= $part['text'];
            }
            if (isset($part['functionCall'])) {
                $toolCalls[] = new ToolCall(
                    id: uniqid('call_'),
                    name: $part['functionCall']['name'] ?? '',
                    arguments: (array)($part['functionCall']['args'] ?? [])
                );
            }
        }

        $usageMeta = $response['usageMetadata'] ?? [];
        $usage = [
            'prompt_tokens' => $usageMeta['promptTokenCount'] ?? 0,
            'completion_tokens' => $usageMeta['candidatesTokenCount'] ?? 0,
            'total_tokens' => $usageMeta['totalTokenCount'] ?? 0,
        ];

        return new ChatResponse(
            content: $content,
            role: 'assistant',
            toolCalls: $toolCalls,
            usage: $usage,
            raw: $response,
            model: $model,
            finishReason: $candidate['finishReason'] ?? null
        );
    }

    public function embed(string|array $input, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultEmbeddingModel;

        if (is_array($input)) {
            $endpoint = "/models/{$model}:batchEmbedContents?key={$this->apiKey}";
            $requests = [];
            foreach ($input as $text) {
                $requests[] = [
                    'model' => "models/{$model}",
                    'content' => ['parts' => [['text' => (string)$text]]],
                ];
            }
            $response = $this->sendRequest($endpoint, ['requests' => $requests]);
            $embeddings = [];
            foreach ($response['embeddings'] ?? [] as $emb) {
                $embeddings[] = $emb['values'] ?? [];
            }
            return $embeddings;
        }

        $endpoint = "/models/{$model}:embedContent?key={$this->apiKey}";
        $payload = [
            'content' => ['parts' => [['text' => $input]]],
        ];

        $response = $this->sendRequest($endpoint, $payload);
        return $response['embedding']['values'] ?? [];
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
            'Content-Type: application/json',
            'Accept: application/json',
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
                throw new AiException("Gemini API Request failed: " . $error);
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
                throw new AiException("Failed to open connection to Gemini API: " . $url);
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
            throw new AiException("Gemini API Error: " . $errMsg);
        }

        return $decoded;
    }
}
