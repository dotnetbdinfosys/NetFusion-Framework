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

namespace NetFusion\Core\Saga;

use NetFusion\Core\Saga\Contracts\SagaStepInterface;

class SagaStep implements SagaStepInterface
{
    /** @var callable */
    protected $action;

    /** @var callable|null */
    protected $compensation;

    protected int $retries = 0;
    protected float $timeout = 30.0;
    protected float $retryDelayMs = 100.0;

    public function __construct(
        protected string $name,
        callable $action,
        ?callable $compensation = null,
        int $retries = 0,
        float $timeout = 30.0
    ) {
        $this->action = $action;
        $this->compensation = $compensation;
        $this->retries = max(0, $retries);
        $this->timeout = max(0.1, $timeout);
    }

    public static function make(
        string $name,
        callable $action,
        ?callable $compensation = null
    ): self {
        return new self($name, $action, $compensation);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function compensateWith(callable $compensation): self
    {
        $this->compensation = $compensation;
        return $this;
    }

    public function retries(int $retries, float $delayMs = 100.0): self
    {
        $this->retries = max(0, $retries);
        $this->retryDelayMs = max(0.0, $delayMs);
        return $this;
    }

    public function timeout(float $seconds): self
    {
        $this->timeout = max(0.1, $seconds);
        return $this;
    }

    public function execute(SagaContext $context): mixed
    {
        return ($this->action)($context);
    }

    public function compensate(SagaContext $context): mixed
    {
        if ($this->compensation !== null) {
            return ($this->compensation)($context);
        }
        return null;
    }

    public function getRetries(): int
    {
        return $this->retries;
    }

    public function getRetryDelayMs(): float
    {
        return $this->retryDelayMs;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }
}
