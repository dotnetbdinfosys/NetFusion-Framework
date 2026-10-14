<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Broadcasting/Contracts/BroadcastDriverInterface.php
| Module    : Broadcasting
| Namespace : NetFusion\Core\Broadcasting\Contracts
| Purpose   : Interface for real-time broadcast transport drivers.
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

namespace NetFusion\Core\Broadcasting\Contracts;

/**
 * Interface BroadcastDriverInterface
 *
 * Defines the contract for broadcast delivery transports (SSE, Log, Null, etc.).
 *
 * @package NetFusion\Core\Broadcasting\Contracts
 */
interface BroadcastDriverInterface
{
    /**
     * Broadcast the given event and payload to the specified channels.
     *
     * @param array<int, ChannelInterface|string> $channels
     * @param string                              $event
     * @param array<string, mixed>                $payload
     */
    public function broadcast(array $channels, string $event, array $payload = []): void;
}
