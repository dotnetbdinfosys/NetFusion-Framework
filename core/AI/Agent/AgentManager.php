<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent;

use NetFusion\Core\AI\Agent\Tools\ToolInterface;
use NetFusion\Core\AI\Agent\Tools\ToolDefinition;
use NetFusion\Core\AI\Agent\Tools\CallableTool;
use NetFusion\Core\AI\Agent\Tools\ReflectionToolSynthesizer;
use NetFusion\Core\AI\Agent\Execution\ToolRegistry;
use NetFusion\Core\AI\Agent\Execution\SandboxedToolDispatcher;
use NetFusion\Core\AI\Agent\Memory\AgenticMemoryFabric;
use NetFusion\Core\AI\Agent\Planning\ReActAgent;

/**
 * AgentManager
 *
 * Central master facade orchestrating autonomous agentic workflows, dynamic tool synthesis,
 * memory fabrics, and ReAct loop executions.
 */
class AgentManager
{
    private ToolRegistry $toolRegistry;
    private SandboxedToolDispatcher $dispatcher;
    private ReflectionToolSynthesizer $synthesizer;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max_iterations' => 10,
            'tool_timeout_seconds' => 30,
            'max_memory_window' => 20,
            'semantic_threshold' => 0.05,
            'compact_observations' => true,
            'default_agent_name' => 'NetFusionAgent',
        ], $config);

        $this->toolRegistry = new ToolRegistry();
        $this->dispatcher = new SandboxedToolDispatcher($this->toolRegistry, [
            'timeout' => $this->config['tool_timeout_seconds'],
        ]);
        $this->synthesizer = new ReflectionToolSynthesizer();
    }

    /**
     * Get the tool registry.
     */
    public function getToolRegistry(): ToolRegistry
    {
        return $this->toolRegistry;
    }

    /**
     * Get the tool dispatcher.
     */
    public function getDispatcher(): SandboxedToolDispatcher
    {
        return $this->dispatcher;
    }

    /**
     * Get the reflection tool synthesizer.
     */
    public function getSynthesizer(): ReflectionToolSynthesizer
    {
        return $this->synthesizer;
    }

    /**
     * Register a tool instance.
     */
    public function registerTool(ToolInterface $tool): self
    {
        $this->toolRegistry->register($tool);
        return $this;
    }

    /**
     * Register a callable/closure as a tool.
     */
    public function registerCallable(string $name, string $description, callable $handler, array $parameters = []): self
    {
        $tool = new CallableTool($name, $description, $handler, $parameters);
        $this->toolRegistry->register($tool);
        return $this;
    }

    /**
     * Synthesize and register tools from an object's public methods.
     */
    public function registerFromObject(object|string $target, ?string $prefix = null): self
    {
        $tools = $this->synthesizer->synthesizeFromClass($target, $prefix);
        foreach ($tools as $tool) {
            $this->toolRegistry->register($tool);
        }
        return $this;
    }

    /**
     * Create a new AgenticMemoryFabric instance.
     */
    public function createMemory(string $agentName = 'default', ?string $storageDir = null): AgenticMemoryFabric
    {
        return new AgenticMemoryFabric([
            'window_size' => $this->config['max_memory_window'],
            'semantic_threshold' => $this->config['semantic_threshold'],
            'compact_observations' => $this->config['compact_observations'],
            'storage_dir' => $storageDir ?? sys_get_temp_dir() . '/netfusion_agent_memory_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $agentName),
        ]);
    }

    /**
     * Create a ReActAgent instance configured with registry, dispatcher, and memory.
     */
    public function createAgent(
        ?callable $llmCallable = null,
        ?string $name = null,
        ?AgenticMemoryFabric $memory = null
    ): ReActAgent {
        $agentName = $name ?? $this->config['default_agent_name'];
        $memoryFabric = $memory ?? $this->createMemory($agentName);

        return new ReActAgent(
            name: $agentName,
            registry: $this->toolRegistry,
            dispatcher: $this->dispatcher,
            memory: $memoryFabric,
            llmCallable: $llmCallable,
            maxIterations: $this->config['max_iterations']
        );
    }
}
