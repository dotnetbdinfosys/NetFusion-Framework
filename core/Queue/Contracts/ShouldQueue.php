<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Queue/Contracts/ShouldQueue.php
| Module    : Core\Queue
| Namespace : NetFusion\Core\Queue\Contracts
| Purpose   : Marker interface indicating that an event listener or job should be queued.
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

namespace NetFusion\Core\Queue\Contracts;

/**
 * NetFusion ShouldQueue Marker Interface
 *
 * When an event listener or job implements this interface, the Event Dispatcher
 * will automatically serialize and push it to the background queue instead of
 * executing it synchronously in the HTTP request cycle.
 *
 * @package NetFusion\Core\Queue\Contracts
 * @version 1.0.0
 */
interface ShouldQueue
{
}
