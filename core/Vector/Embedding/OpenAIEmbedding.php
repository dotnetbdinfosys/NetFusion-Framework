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

namespace NetFusion\Core\Vector\Embedding;

use NetFusion\Core\Vector\Contracts\EmbeddingInterface;
use NetFusion\Core\Vector\Contracts\VectorInterface;
use NetFusion\Core\Vector\Exceptions\EmbeddingException;
use NetFusion\Core\Vector\Math\Vector;

class OpenAIEmbedding implements EmbeddingInterface
{
    /**
     * @param string $apiKey OpenAI API Key
     * @param string $model Embedding model (e.g. text-embedding-3-small, text-embedding-3-large)
     * @param int $dimension Vector dimension (1536 for small, 3072 for large)
     * @param string $baseUrl Base API URL
     */
    public function __construct(
        protected string $apiKey,
        protected string $model = 'text-embedding-3-small',
        protected int $dimension = 1536,
        protected string $baseUrl = 'https://api.openai.com/v1'
    ) {
    }

    public function embedText(string $text): VectorInterface
    {
        $batch = $this->embedBatch([$text]);
        return $batch[0] ?? new Vector(array_fill(0, $this->dimension, 0.0));
    }

    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $url = rtrim($this->baseUrl, '/') . '/embeddings';
        $payload = json_encode([
            'model' => $this->model,
            'input' => array_values($texts),
        ], JSON_THROW_ON_ERROR);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '' || $response === false) {
            throw new EmbeddingException("OpenAI Embedding request failed: {$error}");
        }

        if ($httpCode >= 400) {
            throw new EmbeddingException("OpenAI Embedding returned HTTP {$httpCode}: {$response}");
        }

        $data = json_decode((string)$response, true);
        if (!isset($data['data']) || !is_array($data['data'])) {
            throw new EmbeddingException("Invalid OpenAI Embedding response format");
        }

        $results = [];
        foreach ($data['data'] as $item) {
            $embedding = $item['embedding'] ?? [];
            $results[] = new Vector($embedding);
        }

        return $results;
    }

    public function getDimension(): int
    {
        return $this->dimension;
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
