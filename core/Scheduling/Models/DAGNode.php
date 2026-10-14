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

namespace NetFusion\Core\Scheduling\Models;

class DAGNode
{
    public const STATE_PENDING = 'PENDING';
    public const STATE_RUNNING = 'RUNNING';
    public const STATE_COMPLETED = 'COMPLETED';
    public const STATE_FAILED = 'FAILED';
    public const STATE_SKIPPED = 'SKIPPED';

    private string $id;
    private string $name;
    private mixed $callback;
    private array $dependencies = []; // array of node IDs that must finish first
    private string $state = self::STATE_PENDING;
    private mixed $result = null;
    private ?string $error = null;
    private float $duration = 0.0;
    private ?RetryPolicy $retryPolicy = null;
    private mixed $condition = null;

    public function __construct(string $id, string $name, mixed $callback, array $dependencies = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->callback = $callback;
        $this->dependencies = $dependencies;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCallback(): mixed
    {
        return $this->callback;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function addDependency(string $nodeId): self
    {
        if (!in_array($nodeId, $this->dependencies, true)) {
            $this->dependencies[] = $nodeId;
        }
        return $this;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;
        return $this;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function setResult(mixed $result): self
    {
        $this->result = $result;
        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): self
    {
        $this->error = $error;
        return $this;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function setDuration(float $duration): self
    {
        $this->duration = $duration;
        return $this;
    }

    public function getRetryPolicy(): ?RetryPolicy
    {
        return $this->retryPolicy;
    }

    public function retry(int $maxAttempts, int $delaySeconds = 0, bool $exponential = false, float $multiplier = 2.0): self
    {
        $this->retryPolicy = new RetryPolicy($maxAttempts, $delaySeconds, $exponential, $multiplier);
        return $this;
    }

    public function when(callable $condition): self
    {
        $this->condition = $condition;
        return $this;
    }

    public function getCondition(): mixed
    {
        return $this->condition;
    }

    public function isCompleted(): bool
    {
        return $this->state === self::STATE_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->state === self::STATE_FAILED;
    }

    public function isSkipped(): bool
    {
        return $this->state === self::STATE_SKIPPED;
    }
}
