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

namespace NetFusion\Core\Workflow\Execution;

use NetFusion\Core\Workflow\Contracts\WorkflowInstanceInterface;

class ExecutionContext
{
    protected WorkflowInstanceInterface $instance;
    protected string $currentNodeId;
    /** @var array<string, mixed> */
    protected array $variables;

    public function __construct(
        WorkflowInstanceInterface $instance,
        string $currentNodeId,
        array $variables = []
    ) {
        $this->instance = $instance;
        $this->currentNodeId = $currentNodeId;
        $this->variables = $variables;
    }

    public function getInstance(): WorkflowInstanceInterface
    {
        return $this->instance;
    }

    public function getCurrentNodeId(): string
    {
        return $this->currentNodeId;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->variables[$key] ?? $this->instance->getVariable($key, $default);
    }

    public function set(string $key, mixed $value): self
    {
        $this->variables[$key] = $value;
        $this->instance->setVariable($key, $value);
        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->variables) || $this->instance->getVariable($key) !== null;
    }

    public function all(): array
    {
        return array_merge($this->instance->getVariables(), $this->variables);
    }

    public function merge(array $data): self
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }
}
