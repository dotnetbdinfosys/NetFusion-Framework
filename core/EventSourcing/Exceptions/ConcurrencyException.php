<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/Exceptions/ConcurrencyException.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing\Exceptions
| Purpose   : Exception thrown when optimistic concurrency check fails.
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

class ConcurrencyException extends RuntimeException
{
    public static function versionMismatch(string $streamId, int $expected, int $actual): self
    {
        return new self("Concurrency conflict on stream [{$streamId}]: expected version {$expected}, but found {$actual}.");
    }
}