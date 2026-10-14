<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/Contracts/EventStoreInterface.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing\Contracts
| Purpose   : Interface for appending, loading and replaying event streams.
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

use NetFusion\Core\EventSourcing\EventStream;

interface EventStoreInterface
{
    /**
     * @param string $streamId
     * @param string $aggregateType
     * @param array<DomainEventInterface> $events
     * @param int $expectedVersion
     */
    public function append(string $streamId, string $aggregateType, array $events, int $expectedVersion = -1): void;

    public function loadStream(string $streamId, int $fromVersion = 0): EventStream;

    public function getStreams(?string $aggregateType = null): array;

    public function replayAll(array $projections = []): int;

    public function replayStream(string $streamId, array $projections = []): int;
}