<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/Contracts/DomainEventInterface.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing\Contracts
| Purpose   : Interface defining immutable domain events.
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

interface DomainEventInterface
{
    public function getEventId(): string;
    public function getStreamId(): string;
    public function getAggregateType(): string;
    public function getVersion(): int;
    public function withVersion(int $version): static;
    public function withStreamId(string $streamId): static;
    public function withAggregateType(string $aggregateType): static;
    public function getPayload(): array;
    public function getMetadata(): array;
    public function getOccurredAt(): string;
    public function toArray(): array;
}