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

namespace NetFusion\Core\Actors\Core;

use NetFusion\Core\Actors\Contracts\ActorContextInterface;
use NetFusion\Core\Actors\Contracts\ActorInterface;
use NetFusion\Core\Actors\Contracts\ActorRefInterface;
use NetFusion\Core\Actors\Supervision\OneForOneStrategy;
use NetFusion\Core\Actors\Supervision\SupervisorStrategy;
use RuntimeException;
use Throwable;

abstract class AbstractActor implements ActorInterface
{
    protected ?ActorContextInterface $context = null;

    public function setContext(ActorContextInterface $context): void
    {
        $this->context = $context;
    }

    public function getContext(): ActorContextInterface
    {
        if ($this->context === null) {
            throw new RuntimeException("Actor context has not been initialized.");
        }
        return $this->context;
    }

    public function self(): ActorRefInterface
    {
        return $this->getContext()->self();
    }

    public function sender(): ?ActorRefInterface
    {
        return $this->getContext()->sender();
    }

    public function become(callable $behavior): void
    {
        $this->getContext()->become($behavior);
    }

    public function unbecome(): void
    {
        $this->getContext()->unbecome();
    }

    public function supervisorStrategy(): SupervisorStrategy
    {
        return new OneForOneStrategy();
    }

    public function preStart(): void
    {
        // Default no-op
    }

    public function postStop(): void
    {
        // Default no-op
    }

    public function preRestart(Throwable $reason): void
    {
        $this->postStop();
    }

    public function postRestart(Throwable $reason): void
    {
        $this->preStart();
    }

    abstract public function receive(mixed $message): mixed;
}
