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

class EmbeddingManager
{
    /**
     * @var array<string, EmbeddingInterface>
     */
    protected array $drivers = [];

    /**
     * @var array<string, callable>
     */
    protected array $customCreators = [];

    /**
     * In-memory embedding cache: [cacheKey => VectorInterface]
     * @var array<string, VectorInterface>
     */
    protected array $cache = [];

    protected bool $cacheEnabled = true;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected array $config = []
    ) {
    }

    /**
     * Get default driver name.
     */
    public function getDefaultDriver(): string
    {
        return (string)($this->config['default'] ?? 'mock');
    }

    /**
     * Get embedding driver instance.
     */
    public function driver(?string $name = null): EmbeddingInterface
    {
        $name = $name ?? $this->getDefaultDriver();

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        return $this->drivers[$name] = $this->createDriver($name);
    }

    /**
     * Register a custom embedding driver creator.
     */
    public function extend(string $name, callable $creator): void
    {
        $this->customCreators[$name] = $creator;
    }

    /**
     * Register an existing driver instance.
     */
    public function registerDriver(string $name, EmbeddingInterface $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    /**
     * Create a driver instance by name.
     */
    protected function createDriver(string $name): EmbeddingInterface
    {
        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this->config['drivers'][$name] ?? []);
        }

        $driverConfig = $this->config['drivers'][$name] ?? [];

        return match ($name) {
            'mock' => new MockEmbedding(
                dimension: (int)($driverConfig['dimension'] ?? 64),
                model: (string)($driverConfig['model'] ?? 'mock-embedding-v1')
            ),
            'openai' => new OpenAIEmbedding(
                apiKey: (string)($driverConfig['api_key'] ?? ''),
                model: (string)($driverConfig['model'] ?? 'text-embedding-3-small'),
                dimension: (int)($driverConfig['dimension'] ?? 1536),
                baseUrl: (string)($driverConfig['base_url'] ?? 'https://api.openai.com/v1')
            ),
            'ollama' => new OllamaEmbedding(
                baseUrl: (string)($driverConfig['base_url'] ?? 'http://localhost:11434'),
                model: (string)($driverConfig['model'] ?? 'nomic-embed-text'),
                dimension: (int)($driverConfig['dimension'] ?? 768)
            ),
            'huggingface' => new HuggingFaceEmbedding(
                apiKey: (string)($driverConfig['api_key'] ?? ''),
                model: (string)($driverConfig['model'] ?? 'sentence-transformers/all-MiniLM-L6-v2'),
                dimension: (int)($driverConfig['dimension'] ?? 384)
            ),
            default => throw new EmbeddingException("Unsupported embedding driver [{$name}]"),
        };
    }

    /**
     * Generate embedding with automatic caching.
     */
    public function embed(string $text, ?string $driver = null): VectorInterface
    {
        $driverInstance = $this->driver($driver);
        $driverName = $driver ?? $this->getDefaultDriver();
        $cacheKey = $driverName . ':' . $driverInstance->getModel() . ':' . md5($text);

        if ($this->cacheEnabled && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $vector = $driverInstance->embedText($text);

        if ($this->cacheEnabled) {
            $this->cache[$cacheKey] = $vector;
        }

        return $vector;
    }

    /**
     * Generate embeddings for batch of texts.
     *
     * @param array<string> $texts
     * @return array<VectorInterface>
     */
    public function embedBatch(array $texts, ?string $driver = null): array
    {
        $driverInstance = $this->driver($driver);
        $driverName = $driver ?? $this->getDefaultDriver();
        $results = [];
        $missingTexts = [];
        $missingIndices = [];

        foreach ($texts as $idx => $txt) {
            $cacheKey = $driverName . ':' . $driverInstance->getModel() . ':' . md5($txt);
            if ($this->cacheEnabled && isset($this->cache[$cacheKey])) {
                $results[$idx] = $this->cache[$cacheKey];
            } else {
                $missingTexts[] = $txt;
                $missingIndices[] = $idx;
            }
        }

        if (!empty($missingTexts)) {
            $newVectors = $driverInstance->embedBatch($missingTexts);
            foreach ($newVectors as $i => $vec) {
                $origIndex = $missingIndices[$i];
                $origText = $missingTexts[$i];
                $cacheKey = $driverName . ':' . $driverInstance->getModel() . ':' . md5($origText);

                $results[$origIndex] = $vec;
                if ($this->cacheEnabled) {
                    $this->cache[$cacheKey] = $vec;
                }
            }
        }

        ksort($results);
        return array_values($results);
    }

    public function setCacheEnabled(bool $enabled): void
    {
        $this->cacheEnabled = $enabled;
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }
}
