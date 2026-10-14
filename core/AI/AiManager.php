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

namespace NetFusion\Core\AI;

use InvalidArgumentException;
use NetFusion\Core\AI\Agents\Agent;
use NetFusion\Core\AI\Contracts\LlmDriverInterface;
use NetFusion\Core\AI\Contracts\MemoryInterface;
use NetFusion\Core\AI\Contracts\ToolInterface;
use NetFusion\Core\AI\Contracts\VectorStoreInterface;
use NetFusion\Core\AI\Drivers\AnthropicDriver;
use NetFusion\Core\AI\Drivers\GeminiDriver;
use NetFusion\Core\AI\Drivers\MockAiDriver;
use NetFusion\Core\AI\Drivers\OpenAiDriver;
use NetFusion\Core\AI\Memory\BufferMemory;
use NetFusion\Core\AI\Memory\VectorMemory;
use NetFusion\Core\AI\Memory\WindowedMemory;
use NetFusion\Core\AI\Tools\CalculatorTool;
use NetFusion\Core\AI\Tools\ToolRegistry;
use NetFusion\Core\AI\Vector\ArrayVectorStore;
use NetFusion\Core\AI\Vector\DatabaseVectorStore;
use NetFusion\Core\Application;
use NetFusion\Core\Config\Config;
use NetFusion\Core\Database\DatabaseManager;

class AiManager
{
    /** @var array<string, LlmDriverInterface> */
    protected array $drivers = [];

    /** @var array<string, VectorStoreInterface> */
    protected array $vectorStores = [];

    /** @var array<string, callable> */
    protected array $customDriverCreators = [];

    /** @var array<string, callable> */
    protected array $customVectorStoreCreators = [];

    protected ?ToolRegistry $globalToolRegistry = null;

    public function __construct(
        protected Application $app
    ) {
    }

    /**
     * Get an LLM driver instance by name or default configured driver.
     */
    public function driver(?string $name = null): LlmDriverInterface
    {
        $config = $this->getConfig();
        $name = $name ?? (string)($config['default'] ?? 'mock');

        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->resolveDriver($name, $config['drivers'][$name] ?? []);
        }

        return $this->drivers[$name];
    }

    /**
     * Resolve an LLM driver instance.
     */
    protected function resolveDriver(string $name, array $config): LlmDriverInterface
    {
        if (isset($this->customDriverCreators[$name])) {
            return ($this->customDriverCreators[$name])($this->app, $config);
        }

        return match ($name) {
            'mock' => new MockAiDriver($config['responses'] ?? []),
            'openai' => new OpenAiDriver($config),
            'anthropic' => new AnthropicDriver($config),
            'gemini' => new GeminiDriver($config),
            default => throw new InvalidArgumentException("Unsupported AI LLM driver: [{$name}]"),
        };
    }

    /**
     * Register a custom LLM driver creator.
     */
    public function extendDriver(string $name, callable $creator): self
    {
        $this->customDriverCreators[$name] = $creator;
        return $this;
    }

    /**
     * Get a vector store instance by name or default configured store.
     */
    public function vectorStore(?string $name = null): VectorStoreInterface
    {
        $config = $this->getConfig();
        $name = $name ?? (string)($config['vector_store']['default'] ?? 'array');

        if (!isset($this->vectorStores[$name])) {
            $this->vectorStores[$name] = $this->resolveVectorStore($name, $config['vector_store']['stores'][$name] ?? []);
        }

        return $this->vectorStores[$name];
    }

    /**
     * Resolve a vector store instance.
     */
    protected function resolveVectorStore(string $name, array $config): VectorStoreInterface
    {
        if (isset($this->customVectorStoreCreators[$name])) {
            return ($this->customVectorStoreCreators[$name])($this->app, $config);
        }

        return match ($name) {
            'array' => new ArrayVectorStore(),
            'database' => new DatabaseVectorStore($this->app->make(DatabaseManager::class)),
            default => throw new InvalidArgumentException("Unsupported Vector Store driver: [{$name}]"),
        };
    }

    /**
     * Register a custom vector store creator.
     */
    public function extendVectorStore(string $name, callable $creator): self
    {
        $this->customVectorStoreCreators[$name] = $creator;
        return $this;
    }

    /**
     * Create conversation memory.
     */
    public function memory(string $type = 'buffer', array $options = []): MemoryInterface
    {
        return match ($type) {
            'buffer' => new BufferMemory($options['messages'] ?? []),
            'windowed' => new WindowedMemory((int)($options['window_size'] ?? 10), $options['messages'] ?? []),
            'vector' => new VectorMemory(
                vectorStore: $options['vector_store'] ?? $this->vectorStore(),
                driver: $options['driver'] ?? $this->driver(),
                collection: $options['collection'] ?? 'agent_memory'
            ),
            default => throw new InvalidArgumentException("Unsupported memory type: [{$type}]"),
        };
    }

    /**
     * Get or initialize the global tool registry.
     */
    public function tools(): ToolRegistry
    {
        if ($this->globalToolRegistry === null) {
            $this->globalToolRegistry = new ToolRegistry([
                new CalculatorTool(),
            ]);
        }

        return $this->globalToolRegistry;
    }

    /**
     * Register a tool globally.
     */
    public function registerTool(ToolInterface $tool): self
    {
        $this->tools()->register($tool);
        return $this;
    }

    /**
     * Create and configure an autonomous Agent.
     *
     * @param string $systemPrompt
     * @param array<ToolInterface|string> $tools
     * @param string|null $driver
     * @param string $name
     * @return Agent
     */
    public function agent(
        string $systemPrompt = 'You are a helpful AI assistant.',
        array $tools = [],
        ?string $driver = null,
        string $name = 'DefaultAgent'
    ): Agent {
        $llmDriver = $this->driver($driver);
        $toolRegistry = new ToolRegistry();

        // Copy default tools
        foreach ($this->tools()->getTools() as $t) {
            $toolRegistry->register($t);
        }

        // Add additional requested tools
        foreach ($tools as $tool) {
            if ($tool instanceof ToolInterface) {
                $toolRegistry->register($tool);
            } elseif (is_string($tool) && $this->tools()->has($tool)) {
                $toolRegistry->register($this->tools()->get($tool));
            }
        }

        return new Agent(
            driver: $llmDriver,
            toolRegistry: $toolRegistry,
            memory: new BufferMemory(),
            systemPrompt: $systemPrompt,
            name: $name
        );
    }

    /**
     * Convenience chat completion.
     *
     * @param string|array<ChatMessage|array> $messages
     * @param array<string, mixed> $options
     * @param string|null $driver
     * @return ChatResponse
     */
    public function chat(string|array $messages, array $options = [], ?string $driver = null): ChatResponse
    {
        if (is_string($messages)) {
            $messages = [ChatMessage::user($messages)];
        }

        return $this->driver($driver)->chat($messages, $options);
    }

    /**
     * Convenience text embedding.
     *
     * @param string|array<string> $input
     * @param array<string, mixed> $options
     * @param string|null $driver
     * @return array<float>|array<array<float>>
     */
    public function embed(string|array $input, array $options = [], ?string $driver = null): array
    {
        return $this->driver($driver)->embed($input, $options);
    }

    /**
     * Retrieve configuration array.
     *
     * @return array<string, mixed>
     */
    protected function getConfig(): array
    {
        if ($this->app->has(Config::class)) {
            $config = $this->app->make(Config::class)->get('ai', []);
            if (is_array($config)) {
                return $config;
            }
        }

        return [
            'default' => 'mock',
            'drivers' => [
                'mock' => [],
                'openai' => ['model' => 'gpt-4o-mini', 'embedding_model' => 'text-embedding-3-small'],
                'anthropic' => ['model' => 'claude-3-5-sonnet-20241022'],
                'gemini' => ['model' => 'gemini-1.5-flash'],
            ],
            'vector_store' => [
                'default' => 'array',
                'stores' => [
                    'array' => [],
                    'database' => [],
                ],
            ],
        ];
    }
}
