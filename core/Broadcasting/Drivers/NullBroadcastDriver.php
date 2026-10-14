<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Broadcasting/Drivers/NullBroadcastDriver.php
| Module    : Broadcasting
| Namespace : NetFusion\Core\Broadcasting\Drivers
| Purpose   : No-op broadcast driver for testing and disabled states.
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

namespace NetFusion\Core\Broadcasting\Drivers;

use NetFusion\Core\Broadcasting\Contracts\BroadcastDriverInterface;

/**
 * Class NullBroadcastDriver
 *
 * Silently drops all broadcast events.
 *
 * @package NetFusion\Core\Broadcasting\Drivers
 */
class NullBroadcastDriver implements BroadcastDriverInterface
{
    /**
     * {@inheritdoc}
     */
    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        // No-op
    }
}
