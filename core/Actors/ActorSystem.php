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

namespace NetFusion\Core\Actors;

use NetFusion\Core\Application;
use NetFusion\Core\Actors\Contracts\ActorInterface;
use NetFusion\Core\Actors\Contracts\ActorRefInterface;
use NetFusion\Core\Actors\Core\ActorCell;
use NetFusion\Core\Actors\Core\LocalActorRef;
use NetFusion\Core\Actors\Persistence\AbstractPersistentActor;
use NetFusion\Core\Actors\Persistence\Contracts\ActorStateStoreInterface;
use NetFusion\Core\Actors\Persistence\Store\ArrayActorStateStore;
use SplQueue;

class ActorSystem
{
    /** @var array<string, LocalActorRef> */
    protected array $registry = [];

    /** @var SplQueue<ActorCell> */
    protected SplQueue $dispatchQueue;

    protected DeadLetterQueue $deadLetters;
    protected ActorStateStoreInterface $stateStore;
    protected bool $isTerminated = false;

    /**
     * @param string                         $name
     * @param ActorStateStoreInterface|null  $stateStore
     * @param Application|null               $app
     */
    public function __construct(
        public readonly string $name = 'netfusion',
        ?ActorStateStoreInterface $stateStore = null,
        protected ?Application $app = null
    ) {
        $this->dispatchQueue = new SplQueue();
        $this->deadLetters = new DeadLetterQueue();
        $this->stateStore = $stateStore ?? new ArrayActorStateStore();
    }

    public static function create(string $name = 'netfusion', ?ActorStateStoreInterface $stateStore = null): self
    {
        return new self($name, $stateStore);
    }

    public function getStateStore(): ActorStateStoreInterface
    {
        return $this->stateStore;
    }

    public function setStateStore(ActorStateStoreInterface $stateStore): self
    {
        $this->stateStore = $stateStore;
        return $this;
    }

    public function getDeadLetters(): DeadLetterQueue
    {
        return $this->deadLetters;
    }

    /**
     * Spawn a top-level actor under /user/<name>.
     *
     * @param class-string<ActorInterface> $actorClass
     * @param string                       $name
     * @param array<string, mixed>         $args
     * @return ActorRefInterface
     */
    public function actorOf(string $actorClass, string $name = '', array $args = []): ActorRefInterface
    {
        $name = $name !== '' ? $name : 'actor_' . bin2hex(random_bytes(4));
        $path = "netfusion://{$this->name}/user/{$name}";

        if (isset($this->registry[$path])) {
            return $this->registry[$path];
        }

        $cell = new ActorCell(
            system: $this,
            actorClass: $actorClass,
            path: $path,
            name: $name,
            args: $args
        );

        $cell->start();
        $this->registerActor($cell->selfRef);

        return $cell->selfRef;
    }

    /**
     * Retrieve or activate a Virtual Stateful Actor dynamically on demand.
     *
     * @param class-string<AbstractPersistentActor> $actorClass
     * @param string                                $id
     * @param array<string, mixed>                  $args
     * @return ActorRefInterface
     */
    public function virtual(string $actorClass, string $id, array $args = []): ActorRefInterface
    {
        $name = 'v_' . $id;
        $path = "netfusion://{$this->name}/user/{$name}";

        if (isset($this->registry[$path]) && $this->registry[$path]->isAlive()) {
            return $this->registry[$path];
        }

        $ref = $this->actorOf($actorClass, $name, $args);

        // Auto-inject state store and recover
        $cell = $this->registry[$path]->getCell();
        // Trigger recovery if actor instance is persistent
        $reflection = new \ReflectionClass($cell);
        $instanceProp = $reflection->getProperty('actorInstance');
        $instanceProp->setAccessible(true);
        $instance = $instanceProp->getValue($cell);

        if ($instance instanceof AbstractPersistentActor) {
            $instance->setStateStore($this->stateStore);
            $instance->recoverState();
        }

        return $ref;
    }

    /**
     * Find an active actor by exact path or short name.
     */
    public function actorSelection(string $path): ?ActorRefInterface
    {
        if (isset($this->registry[$path])) {
            return $this->registry[$path];
        }

        // Relative path resolution /user/name or just name
        $userPath = "netfusion://{$this->name}/user/" . ltrim($path, '/');
        if (isset($this->registry[$userPath])) {
            return $this->registry[$userPath];
        }

        return null;
    }

    public function registerActor(LocalActorRef $ref): void
    {
        $this->registry[$ref->path()] = $ref;
    }

    public function unregisterActor(string $path): void
    {
        unset($this->registry[$path]);
    }

    /**
     * Get all active registered actors.
     *
     * @return array<string, ActorRefInterface>
     */
    public function allActors(): array
    {
        return $this->registry;
    }

    /**
     * Schedule an actor cell for mailbox processing.
     */
    public function scheduleProcessing(ActorCell $cell): void
    {
        $this->dispatchQueue->enqueue($cell);
    }

    /**
     * Process scheduled mailboxes until idle.
     */
    public function runUntilIdle(int $maxIterations = 1000): int
    {
        $totalProcessed = 0;
        $iterations = 0;

        while (!$this->dispatchQueue->isEmpty() && $iterations < $maxIterations) {
            $cell = $this->dispatchQueue->dequeue();
            if (!$cell->isTerminated()) {
                $processed = $cell->processMailbox();
                $totalProcessed += $processed;
                if ($cell->getMailbox()->hasMessages()) {
                    $this->dispatchQueue->enqueue($cell);
                }
            }
            $iterations++;
        }

        return $totalProcessed;
    }

    /**
     * Send message using short helper tell syntax.
     */
    public function tell(string $path, mixed $message, ?ActorRefInterface $sender = null): bool
    {
        $ref = $this->actorSelection($path);
        if ($ref === null) {
            $this->deadLetters->publish(
                \NetFusion\Core\Actors\Core\DeadLetter::create($message, $sender, null, "Actor '{$path}' not found")
            );
            return false;
        }

        $ref->tell($message, $sender);
        $this->runUntilIdle();
        return true;
    }

    /**
     * Send ask request using short helper ask syntax.
     */
    public function ask(string $path, mixed $message, float $timeout = 5.0): mixed
    {
        $ref = $this->actorSelection($path);
        if ($ref === null) {
            throw new \RuntimeException("Actor '{$path}' not found for ask request.");
        }

        return $ref->ask($message, $timeout);
    }

    /**
     * Terminate the entire actor system.
     */
    public function terminate(): void
    {
        foreach ($this->registry as $ref) {
            $ref->getCell()->stop();
        }
        $this->registry = [];
        $this->isTerminated = true;
    }

    public function isTerminated(): bool
    {
        return $this->isTerminated;
    }
}
