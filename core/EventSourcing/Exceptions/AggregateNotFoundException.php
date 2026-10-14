<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/Exceptions/AggregateNotFoundException.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing\Exceptions
| Purpose   : Exception thrown when an aggregate stream cannot be found.
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

namespace NetFusion\Core\EventSourcing\Exceptions;

use RuntimeException;

class AggregateNotFoundException extends RuntimeException
{
    public static function forId(string $aggregateClass, string $id): self
    {
        return new self("Aggregate [{$aggregateClass}] with ID [{$id}] was not found in the event store.");
    }
}