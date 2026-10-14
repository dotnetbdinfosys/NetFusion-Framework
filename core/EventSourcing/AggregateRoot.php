<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/AggregateRoot.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing
| Purpose   : Base Aggregate Root supporting Event Sourcing and State Mutators.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\EventSourcing;

use NetFusion\Core\EventSourcing\Contracts\DomainEventInterface;
use ReflectionClass;

abstract class AggregateRoot
{
    protected string $id = '';
    protected int $version = 0;

    /**
     * @var array<DomainEventInterface>
     */
    protected array $uncommittedEvents = [];

    public function getId(): string
    {
        return $this->id;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getAggregateType(): string
    {
        return static::class;
    }

    /**
     * Records a new domain event and applies it immediately to state.
     */
    protected function recordThat(DomainEventInterface $event): void
    {
        $this->version++;
        $event = $event->withStreamId($this->id)
            ->withAggregateType($this->getAggregateType())
            ->withVersion($this->version);

        $this->apply($event);
        $this->uncommittedEvents[] = $event;
    }

    /**
     * Mutates internal state by dispatching to apply<EventName> handler.
     */
    public function apply(DomainEventInterface $event): void
    {
        $shortName = (new ReflectionClass($event))->getShortName();
        $method = 'apply' . $shortName;

        if (method_exists($this, $method)) {
            $this->{$method}($event);
        }
    }

    /**
     * Reconstitutes the Aggregate Root from past event history.
     */
    public static function reconstituteFromHistory(EventStream $stream): static
    {
        $aggregate = new static();
        $aggregate->id = $stream->getStreamId();

        foreach ($stream->getEvents() as $event) {
            $aggregate->version = $event->getVersion();
            $aggregate->apply($event);
        }

        return $aggregate;
    }

    /**
     * @return array<DomainEventInterface>
     */
    public function getUncommittedEvents(): array
    {
        return $this->uncommittedEvents;
    }

    public function clearUncommittedEvents(): void
    {
        $this->uncommittedEvents = [];
    }
}