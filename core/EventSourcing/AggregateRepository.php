<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/AggregateRepository.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing
| Purpose   : Repository for loading and persisting Aggregate Roots.
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

use NetFusion\Core\EventSourcing\Contracts\AggregateRepositoryInterface;
use NetFusion\Core\EventSourcing\Contracts\EventStoreInterface;
use NetFusion\Core\EventSourcing\Exceptions\AggregateNotFoundException;

class AggregateRepository implements AggregateRepositoryInterface
{
    public function __construct(protected EventStoreInterface $eventStore)
    {
    }

    public function load(string $aggregateClass, string $id): AggregateRoot
    {
        $stream = $this->eventStore->loadStream($id);

        if ($stream->isEmpty()) {
            throw AggregateNotFoundException::forId($aggregateClass, $id);
        }

        return $aggregateClass::reconstituteFromHistory($stream);
    }

    public function save(AggregateRoot $aggregate): void
    {
        $events = $aggregate->getUncommittedEvents();
        if (empty($events)) {
            return;
        }

        $expectedVersion = $aggregate->getVersion() - count($events);
        $this->eventStore->append(
            $aggregate->getId(),
            $aggregate->getAggregateType(),
            $events,
            $expectedVersion
        );

        $aggregate->clearUncommittedEvents();
    }
}