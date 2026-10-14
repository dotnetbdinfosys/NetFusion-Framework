<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/Contracts/AggregateRepositoryInterface.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing\Contracts
| Purpose   : Contract for loading and persisting Aggregate Roots.
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

namespace NetFusion\Core\EventSourcing\Contracts;

use NetFusion\Core\EventSourcing\AggregateRoot;

interface AggregateRepositoryInterface
{
    /**
     * @template T of AggregateRoot
     * @param class-string<T> $aggregateClass
     * @param string $id
     * @return T
     */
    public function load(string $aggregateClass, string $id): AggregateRoot;

    public function save(AggregateRoot $aggregate): void;
}