<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/Contracts/ProjectionInterface.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing\Contracts
| Purpose   : Interface for asynchronous and synchronous projections (Read Models).
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

interface ProjectionInterface
{
    public function project(DomainEventInterface $event): void;
    public function reset(): void;
    public function getName(): string;
}