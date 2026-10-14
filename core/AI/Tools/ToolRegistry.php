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

namespace NetFusion\Core\AI\Tools;

use InvalidArgumentException;
use NetFusion\Core\AI\Contracts\ToolInterface;

class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    protected array $tools = [];

    /**
     * @param ToolInterface[] $tools
     */
    public function __construct(array $tools = [])
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function register(ToolInterface $tool): self
    {
        $this->tools[$tool->getName()] = $tool;
        return $this;
    }

    public function registerCallable(
        string $name,
        string $description,
        array $schema,
        callable $handler
    ): self {
        return $this->register(new CustomTool($name, $description, $schema, $handler));
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    public function execute(string $name, array $arguments = []): mixed
    {
        $tool = $this->get($name);
        if ($tool === null) {
            throw new InvalidArgumentException("Tool '{$name}' is not registered.");
        }

        return $tool->execute($arguments);
    }

    /**
     * @return array<string, ToolInterface>
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function toFunctionDefinitions(): array
    {
        $definitions = [];
        foreach ($this->tools as $tool) {
            $definitions[] = $tool->toFunctionDefinition();
        }
        return $definitions;
    }

    public function count(): int
    {
        return count($this->tools);
    }
}
