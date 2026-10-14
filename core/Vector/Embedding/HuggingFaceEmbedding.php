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

class HuggingFaceEmbedding implements EmbeddingInterface
{
    /**
     * @param string $apiKey Hugging Face API Token
     * @param string $model Model repository ID (e.g. sentence-transformers/all-MiniLM-L6-v2)
     * @param int $dimension Vector dimension (e.g. 384)
     */
    public function __construct(
        protected string $apiKey,
        protected string $model = 'sentence-transformers/all-MiniLM-L6-v2',
        protected int $dimension = 384
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

        $url = 'https://api-inference.huggingface.co/pipeline/feature-extraction/' . $this->model;
        $payload = json_encode(['inputs' => array_values($texts)], JSON_THROW_ON_ERROR);

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
            throw new EmbeddingException("HuggingFace Embedding request failed: {$error}");
        }

        if ($httpCode >= 400) {
            throw new EmbeddingException("HuggingFace returned HTTP {$httpCode}: {$response}");
        }

        $data = json_decode((string)$response, true);
        if (!is_array($data)) {
            throw new EmbeddingException("Invalid HuggingFace response format");
        }

        // Feature extraction returns array of float arrays or 1D array if single item
        $results = [];
        if (isset($data[0]) && is_array($data[0])) {
            foreach ($data as $vecArr) {
                $results[] = new Vector((array)$vecArr);
            }
        } else {
            $results[] = new Vector($data);
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
