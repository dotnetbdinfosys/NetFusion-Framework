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

class OpenAiDriver implements LlmDriverInterface
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $defaultModel;
    protected string $defaultEmbeddingModel;
    protected int $timeout;
    protected array $customHeaders;

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? (string)getenv('OPENAI_API_KEY');
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.openai.com/v1', '/');
        $this->defaultModel = $config['model'] ?? 'gpt-4o-mini';
        $this->defaultEmbeddingModel = $config['embedding_model'] ?? 'text-embedding-3-small';
        $this->timeout = (int)($config['timeout'] ?? 30);
        $this->customHeaders = $config['headers'] ?? [];
    }

    public function chat(array $messages, array $options = []): ChatResponse
    {
        $formattedMessages = [];
        foreach ($messages as $msg) {
            if ($msg instanceof ChatMessage) {
                $formattedMessages[] = $msg->toArray();
            } elseif (is_array($msg)) {
                $formattedMessages[] = $msg;
            }
        }

        $payload = [
            'model' => $options['model'] ?? $this->defaultModel,
            'messages' => $formattedMessages,
        ];

        if (isset($options['temperature'])) {
            $payload['temperature'] = (float)$options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int)$options['max_tokens'];
        }
        if (isset($options['tools']) && !empty($options['tools'])) {
            $payload['tools'] = $options['tools'];
            if (isset($options['tool_choice'])) {
                $payload['tool_choice'] = $options['tool_choice'];
            }
        }
        if (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        $response = $this->sendRequest('/chat/completions', $payload);

        $choice = $response['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $content = $message['content'] ?? '';
        $role = $message['role'] ?? 'assistant';
        $finishReason = $choice['finish_reason'] ?? null;
        $usage = $response['usage'] ?? [];
        $model = $response['model'] ?? ($payload['model']);

        $toolCalls = [];
        if (!empty($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $tc) {
                $funcName = $tc['function']['name'] ?? '';
                $rawArgs = $tc['function']['arguments'] ?? '{}';
                $args = is_string($rawArgs) ? (json_decode($rawArgs, true) ?? []) : (array)$rawArgs;
                $toolCalls[] = new ToolCall(
                    id: $tc['id'] ?? uniqid('call_'),
                    name: $funcName,
                    arguments: $args
                );
            }
        }

        return new ChatResponse(
            content: (string)$content,
            role: (string)$role,
            toolCalls: $toolCalls,
            usage: $usage,
            raw: $response,
            model: $model,
            finishReason: $finishReason
        );
    }

    public function embed(string|array $input, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->defaultEmbeddingModel,
            'input' => $input,
        ];

        if (isset($options['dimensions'])) {
            $payload['dimensions'] = (int)$options['dimensions'];
        }

        $response = $this->sendRequest('/embeddings', $payload);

        if (is_array($input)) {
            $embeddings = [];
            foreach ($response['data'] ?? [] as $item) {
                $embeddings[] = $item['embedding'] ?? [];
            }
            return $embeddings;
        }

        return $response['data'][0]['embedding'] ?? [];
    }

    /**
     * Send HTTP POST request to OpenAI compatible API.
     *
     * @param string $endpoint
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws AiException
     */
    protected function sendRequest(string $endpoint, array $payload): array
    {
        $url = $this->baseUrl . $endpoint;
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $headers = array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
        ], $this->customHeaders);

        if (!empty($this->apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

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
                throw new AiException("OpenAI API Request failed: " . $error);
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
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $result = @file_get_contents($url, false, $context);
            if ($result === false) {
                throw new AiException("Failed to open connection to OpenAI API: " . $url);
            }

            $httpCode = 200;
            if (isset($http_response_header[0])) {
                preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
                $httpCode = isset($matches[1]) ? (int)$matches[1] : 200;
            }
        }

        $decoded = json_decode($result, true);
        if ($httpCode >= 400 || !is_array($decoded)) {
            $errMsg = $decoded['error']['message'] ?? $decoded['message'] ?? ("HTTP " . $httpCode . ": " . substr($result, 0, 200));
            throw new AiException("OpenAI API Error: " . $errMsg);
        }

        return $decoded;
    }
}
