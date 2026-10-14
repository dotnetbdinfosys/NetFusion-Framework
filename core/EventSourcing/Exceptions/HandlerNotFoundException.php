<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/Exceptions/HandlerNotFoundException.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing\Exceptions
| Purpose   : Exception thrown when no handler is mapped for a command or query.
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

class HandlerNotFoundException extends RuntimeException
{
    public static function forCommand(string $commandClass): self
    {
        return new self("No command handler registered for [{$commandClass}].");
    }

    public static function forQuery(string $queryClass): self
    {
        return new self("No query handler registered for [{$queryClass}].");
    }
}