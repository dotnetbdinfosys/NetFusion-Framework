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

use NetFusion\Core\Saga\Contracts\SagaInterface;
use NetFusion\Core\Saga\Contracts\SagaStepInterface;
use Throwable;

class SagaBuilder implements SagaInterface
{
    /** @var SagaStepInterface[] */
    protected array $steps = [];

    /** @var callable|null */
    protected $startHandler = null;

    /** @var callable|null */
    protected $completeHandler = null;

    /** @var callable|null */
    protected $compensatingHandler = null;

    /** @var callable|null */
    protected $compensatedHandler = null;

    public function __construct(
        protected string $name
    ) {
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Add a step fluently and return the created SagaStep for chaining retries/compensation.
     */
    public function step(string $name, callable $action, ?callable $compensation = null): SagaStep
    {
        $step = new SagaStep($name, $action, $compensation);
        $this->steps[] = $step;
        return $step;
    }

    public function addStep(SagaStepInterface $step): self
    {
        $this->steps[] = $step;
        return $this;
    }

    public function whenStarting(callable $hook): self
    {
        $this->startHandler = $hook;
        return $this;
    }

    public function whenCompleted(callable $hook): self
    {
        $this->completeHandler = $hook;
        return $this;
    }

    public function whenCompensating(callable $hook): self
    {
        $this->compensatingHandler = $hook;
        return $this;
    }

    public function whenCompensated(callable $hook): self
    {
        $this->compensatedHandler = $hook;
        return $this;
    }

    // Aliases
    public function onStartHook(callable $hook): self
    {
        return $this->whenStarting($hook);
    }

    public function onCompleteHook(callable $hook): self
    {
        return $this->whenCompleted($hook);
    }

    public function onCompensatingHook(callable $hook): self
    {
        return $this->whenCompensating($hook);
    }

    public function onCompensatedHook(callable $hook): self
    {
        return $this->whenCompensated($hook);
    }

    /**
     * @return SagaStepInterface[]
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    public function onStart(SagaContext $context): void
    {
        if ($this->startHandler !== null) {
            ($this->startHandler)($context);
        }
    }

    public function onComplete(SagaContext $context): void
    {
        if ($this->completeHandler !== null) {
            ($this->completeHandler)($context);
        }
    }

    public function onCompensating(SagaContext $context, Throwable $error): void
    {
        if ($this->compensatingHandler !== null) {
            ($this->compensatingHandler)($context, $error);
        }
    }

    public function onCompensated(SagaContext $context): void
    {
        if ($this->compensatedHandler !== null) {
            ($this->compensatedHandler)($context);
        }
    }
}
