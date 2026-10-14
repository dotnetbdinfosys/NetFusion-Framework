<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/EventSourcingManager.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing
| Purpose   : Central Facade orchestrating EventStore, Projections, CommandBus & QueryBus.
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

use NetFusion\Core\EventSourcing\Contracts\CommandInterface;
use NetFusion\Core\EventSourcing\Contracts\EventStoreInterface;
use NetFusion\Core\EventSourcing\Contracts\ProjectionInterface;
use NetFusion\Core\EventSourcing\Contracts\QueryInterface;

class EventSourcingManager
{
    public function __construct(
        protected EventStoreInterface $eventStore,
        protected ProjectionManager $projections,
        protected CommandBus $commandBus,
        protected QueryBus $queryBus,
        protected AggregateRepository $repository
    ) {
    }

    public function getStore(): EventStoreInterface
    {
        return $this->eventStore;
    }

    public function getProjections(): ProjectionManager
    {
        return $this->projections;
    }

    public function getCommandBus(): CommandBus
    {
        return $this->commandBus;
    }

    public function getQueryBus(): QueryBus
    {
        return $this->queryBus;
    }

    public function getRepository(): AggregateRepository
    {
        return $this->repository;
    }

    public function registerProjection(ProjectionInterface $projection): self
    {
        $this->projections->register($projection);
        return $this;
    }

    public function dispatch(CommandInterface $command): mixed
    {
        return $this->commandBus->dispatch($command);
    }

    public function ask(QueryInterface $query): mixed
    {
        return $this->queryBus->ask($query);
    }

    public function save(AggregateRoot $aggregate): void
    {
        $this->repository->save($aggregate);
    }

    public function load(string $aggregateClass, string $id): AggregateRoot
    {
        return $this->repository->load($aggregateClass, $id);
    }
}