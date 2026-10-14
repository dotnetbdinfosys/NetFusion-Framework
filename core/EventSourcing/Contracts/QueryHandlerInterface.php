<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/Contracts/QueryHandlerInterface.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing\Contracts
| Purpose   : Handler contract for CQRS queries.
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

interface QueryHandlerInterface
{
    public function handle(QueryInterface $query): mixed;
}