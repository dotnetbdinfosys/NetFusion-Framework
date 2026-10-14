<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/EventStream.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing
| Purpose   : Value object representing an ordered stream of domain events.
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

use ArrayIterator;
use Countable;
use IteratorAggregate;
use NetFusion\Core\EventSourcing\Contracts\DomainEventInterface;
use Traversable;

class EventStream implements IteratorAggregate, Countable
{
    /**
     * @param string $streamId
     * @param string $aggregateType
     * @param array<DomainEventInterface> $events
     * @param int $version
     */
    public function __construct(
        protected string $streamId,
        protected string $aggregateType,
        protected array $events = [],
        protected int $version = 0
    ) {
    }

    public function getStreamId(): string
    {
        return $this->streamId;
    }

    public function getAggregateType(): string
    {
        return $this->aggregateType;
    }

    /**
     * @return array<DomainEventInterface>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function isEmpty(): bool
    {
        return empty($this->events);
    }

    public function count(): int
    {
        return count($this->events);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->events);
    }
}