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

use NetFusion\Core\Actors\ActorSystem;
use NetFusion\Core\Actors\Contracts\ActorContextInterface;
use NetFusion\Core\Actors\Contracts\ActorInterface;
use NetFusion\Core\Actors\Contracts\ActorRefInterface;
use NetFusion\Core\Actors\Contracts\MailboxInterface;
use NetFusion\Core\Actors\Mailbox\UnboundedMailbox;
use NetFusion\Core\Actors\Supervision\SupervisorStrategy;
use Throwable;

class ActorCell
{
    public LocalActorRef $selfRef;
    protected ?ActorInterface $actorInstance = null;
    protected MailboxInterface $mailbox;
    /** @var array<string, LocalActorRef> */
    protected array $children = [];
    /** @var list<callable> */
    protected array $behaviorStack = [];
    protected ?ActorRefInterface $currentSender = null;
    protected bool $isTerminated = false;
    protected int $restartCount = 0;
    protected float $firstRestartTime = 0.0;

    /**
     * @param ActorSystem                  $system
     * @param class-string<ActorInterface> $actorClass
     * @param string                       $path
     * @param string                       $name
     * @param array<string, mixed>         $args
     * @param ActorRefInterface|null       $parentRef
     * @param MailboxInterface|null        $mailbox
     */
    public function __construct(
        public readonly ActorSystem $system,
        public readonly string $actorClass,
        public readonly string $path,
        public readonly string $name,
        public readonly array $args = [],
        public readonly ?ActorRefInterface $parentRef = null,
        ?MailboxInterface $mailbox = null
    ) {
        $this->mailbox = $mailbox ?? new UnboundedMailbox();
        $this->selfRef = new LocalActorRef($this);
    }

    public function start(): void
    {
        if ($this->isTerminated) {
            return;
        }

        $this->actorInstance = $this->createActorInstance();
        $context = new ActorContext($this);
        $this->actorInstance->setContext($context);

        try {
            $this->actorInstance->preStart();
        } catch (Throwable $e) {
            $this->handleFailure($e);
        }
    }

    public function stop(): void
    {
        if ($this->isTerminated) {
            return;
        }

        // Stop all children first
        foreach ($this->children as $child) {
            $child->getCell()->stop();
        }
        $this->children = [];

        if ($this->actorInstance !== null) {
            try {
                $this->actorInstance->postStop();
            } catch (Throwable) {
                // Ignore errors during postStop
            }
        }

        $this->mailbox->clear();
        $this->isTerminated = true;
        $this->actorInstance = null;
    }

    public function restart(Throwable $reason): void
    {
        if ($this->isTerminated) {
            return;
        }

        if ($this->actorInstance !== null) {
            try {
                $this->actorInstance->preRestart($reason);
            } catch (Throwable) {
                // Ignore preRestart failure
            }
        }

        // Stop children
        foreach ($this->children as $child) {
            $child->getCell()->stop();
        }
        $this->children = [];
        $this->behaviorStack = [];

        // Recreate instance
        $this->actorInstance = $this->createActorInstance();
        $this->actorInstance->setContext(new ActorContext($this));

        try {
            $this->actorInstance->postRestart($reason);
        } catch (Throwable) {
            // Ignore postRestart failure
        }
    }

    public function isTerminated(): bool
    {
        return $this->isTerminated;
    }

    public function getMailbox(): MailboxInterface
    {
        return $this->mailbox;
    }

    public function getCurrentSender(): ?ActorRefInterface
    {
        return $this->currentSender;
    }

    public function getChildren(): array
    {
        return array_values($this->children);
    }

    public function getChild(string $name): ?LocalActorRef
    {
        return $this->children[$name] ?? null;
    }

    public function addChild(LocalActorRef $child): void
    {
        $this->children[$child->name()] = $child;
    }

    public function removeChild(string $name): void
    {
        unset($this->children[$name]);
    }

    public function become(callable $behavior): void
    {
        $this->behaviorStack[] = $behavior;
    }

    public function unbecome(): void
    {
        if (!empty($this->behaviorStack)) {
            array_pop($this->behaviorStack);
        }
    }

    public function enqueue(Envelope $envelope): void
    {
        if ($this->isTerminated) {
            $this->system->getDeadLetters()->publish(
                DeadLetter::create($envelope->message, $envelope->sender, $this->selfRef, 'Actor is stopped')
            );
            return;
        }

        $this->mailbox->enqueue($envelope);
        $this->system->scheduleProcessing($this);
    }

    /**
     * Process a single envelope.
     */
    public function processEnvelope(Envelope $envelope): mixed
    {
        if ($this->isTerminated || $this->actorInstance === null) {
            $this->system->getDeadLetters()->publish(
                DeadLetter::create($envelope->message, $envelope->sender, $this->selfRef, 'Actor is stopped')
            );
            return null;
        }

        $this->currentSender = $envelope->sender;
        $response = null;

        try {
            if (!empty($this->behaviorStack)) {
                $currentBehavior = end($this->behaviorStack);
                $response = $currentBehavior($envelope->message);
            } else {
                $response = $this->actorInstance->receive($envelope->message);
            }
        } catch (Throwable $e) {
            $this->handleFailure($e);
        } finally {
            $this->currentSender = null;
        }

        return $response;
    }

    /**
     * Process pending messages in mailbox up to $maxMessages.
     */
    public function processMailbox(int $maxMessages = 50): int
    {
        $processed = 0;
        while ($processed < $maxMessages && $this->mailbox->hasMessages() && !$this->isTerminated) {
            $env = $this->mailbox->dequeue();
            if ($env !== null) {
                $this->processEnvelope($env);
                $processed++;
            }
        }
        return $processed;
    }

    public function handleChildFailure(LocalActorRef $childRef, Throwable $e): void
    {
        $strategy = ($this->actorInstance instanceof AbstractActor)
            ? $this->actorInstance->supervisorStrategy()
            : new \NetFusion\Core\Actors\Supervision\OneForOneStrategy();

        $directive = $strategy->handle($e);

        if ($strategy->isOneForOne()) {
            $this->applyDirectiveToChild($childRef, $directive, $e);
        } else {
            // All-For-One: apply to all children
            foreach ($this->children as $child) {
                $this->applyDirectiveToChild($child, $directive, $e);
            }
        }
    }

    protected function applyDirectiveToChild(LocalActorRef $childRef, string $directive, Throwable $e): void
    {
        $childCell = $childRef->getCell();
        match ($directive) {
            SupervisorStrategy::RESUME => null, // ignore and continue
            SupervisorStrategy::RESTART => $childCell->restart($e),
            SupervisorStrategy::STOP => $childCell->stop(),
            SupervisorStrategy::ESCALATE => $this->handleFailure($e),
            default => $childCell->restart($e),
        };
    }

    protected function handleFailure(Throwable $e): void
    {
        if ($this->parentRef instanceof LocalActorRef) {
            $this->parentRef->getCell()->handleChildFailure($this->selfRef, $e);
        } else {
            // Top level actor under system: default restart
            $this->restart($e);
        }
    }

    protected function createActorInstance(): ActorInterface
    {
        $class = $this->actorClass;
        if (!empty($this->args)) {
            return new $class(...$this->args);
        }
        return new $class();
    }
}
