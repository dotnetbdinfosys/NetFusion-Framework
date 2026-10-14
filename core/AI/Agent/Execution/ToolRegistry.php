<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Execution;

use NetFusion\Core\AI\Agent\Tools\ToolInterface;

class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    /**
     * @param ToolInterface[] $initialTools
     */
    public function __construct(array $initialTools = [])
    {
        foreach ($initialTools as $tool) {
            $this->register($tool);
        }
    }

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    /**
     * @param iterable<ToolInterface> $tools
     */
    public function registerAll(iterable $tools): void
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function count(): int
    {
        return count($this->tools);
    }

    public function unregister(string $name): bool
    {
        if (isset($this->tools[$name])) {
            unset($this->tools[$name]);
            return true;
        }
        return false;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return array<string, ToolInterface>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Returns standard JSON function calling schemas for LLM prompts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toFunctionSchemas(): array
    {
        $schemas = [];
        foreach ($this->tools as $tool) {
            $schemas[] = $tool->getDefinition()->toJsonSchema();
        }
        return $schemas;
    }
}
