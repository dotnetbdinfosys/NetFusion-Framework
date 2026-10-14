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

class OllamaEmbedding implements EmbeddingInterface
{
    /**
     * @param string $baseUrl Ollama host URL (e.g. http://localhost:11434)
     * @param string $model Embedding model (e.g. nomic-embed-text, bge-m3, all-minilm)
     * @param int $dimension Vector dimension (e.g. 768)
     */
    public function __construct(
        protected string $baseUrl = 'http://localhost:11434',
        protected string $model = 'nomic-embed-text',
        protected int $dimension = 768
    ) {
    }

    public function embedText(string $text): VectorInterface
    {
        $url = rtrim($this->baseUrl, '/') . '/api/embeddings';
        $payload = json_encode([
            'model' => $this->model,
            'prompt' => $text,
        ], JSON_THROW_ON_ERROR);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '' || $response === false) {
            throw new EmbeddingException("Ollama Embedding request failed: {$error}");
        }

        if ($httpCode >= 400) {
            throw new EmbeddingException("Ollama returned HTTP {$httpCode}: {$response}");
        }

        $data = json_decode((string)$response, true);
        $embedding = $data['embedding'] ?? [];
        if (empty($embedding)) {
            throw new EmbeddingException("Empty embedding returned by Ollama");
        }

        $vec = new Vector($embedding);
        $this->dimension = $vec->dimension();
        return $vec;
    }

    public function embedBatch(array $texts): array
    {
        $res = [];
        foreach ($texts as $text) {
            $res[] = $this->embedText((string)$text);
        }
        return $res;
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
