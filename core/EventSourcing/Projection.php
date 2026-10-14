<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/Projection.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing
| Purpose   : Abstract base class for CQRS Projections (Read Models).
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
use NetFusion\Core\EventSourcing\Contracts\ProjectionInterface;
use ReflectionClass;

abstract class Projection implements ProjectionInterface
{
    public function getName(): string
    {
        return (new ReflectionClass($this))->getShortName();
    }

    public function project(DomainEventInterface $event): void
    {
        $shortName = (new ReflectionClass($event))->getShortName();
        $method = 'on' . $shortName;

        if (method_exists($this, $method)) {
            $this->{$method}($event);
        }
    }

    public function reset(): void
    {
        // Default empty reset. Child projections can truncate tables or clear cache.
    }
}