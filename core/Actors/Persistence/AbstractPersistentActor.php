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

namespace NetFusion\Core\Actors\Persistence;

use NetFusion\Core\Actors\Core\AbstractActor;
use NetFusion\Core\Actors\Persistence\Contracts\ActorStateStoreInterface;

abstract class AbstractPersistentActor extends AbstractActor
{
    protected int $lastSequenceNumber = 0;
    protected ?ActorStateStoreInterface $stateStore = null;

    abstract public function persistenceId(): string;

    public function setStateStore(ActorStateStoreInterface $store): void
    {
        $this->stateStore = $store;
    }

    public function getStateStore(): ?ActorStateStoreInterface
    {
        return $this->stateStore;
    }

    public function getLastSequenceNumber(): int
    {
        return $this->lastSequenceNumber;
    }

    /**
     * Persist an event and execute handler upon successful append.
     */
    public function persist(mixed $event, callable $handler): void
    {
        $this->lastSequenceNumber++;
        if ($this->stateStore !== null) {
            $this->stateStore->appendEvent($this->persistenceId(), $this->lastSequenceNumber, $event);
        }

        $handler($event);
    }

    /**
     * Save a snapshot of the current state.
     */
    public function saveSnapshot(mixed $state): bool
    {
        if ($this->stateStore !== null) {
            return $this->stateStore->saveSnapshot($this->persistenceId(), $this->lastSequenceNumber, $state);
        }
        return false;
    }

    /**
     * Recover actor state from snapshot and journal events.
     */
    public function recoverState(?ActorStateStoreInterface $store = null): void
    {
        $store = $store ?? $this->stateStore;
        if ($store === null) {
            return;
        }

        $fromSeq = 1;
        $snapshot = $store->loadSnapshot($this->persistenceId());
        if ($snapshot !== null) {
            $this->lastSequenceNumber = $snapshot['sequence'];
            $fromSeq = $snapshot['sequence'] + 1;
            $this->receiveRecover($snapshot['state'], true);
        }

        $events = $store->getEvents($this->persistenceId(), $fromSeq);
        foreach ($events as $entry) {
            $this->lastSequenceNumber = $entry['sequence'];
            $this->receiveRecover($entry['event'], false);
        }
    }

    /**
     * Handle state recovery from snapshot or event.
     *
     * @param mixed $data
     * @param bool  $isSnapshot
     */
    abstract public function receiveRecover(mixed $data, bool $isSnapshot = false): void;
}
