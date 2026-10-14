<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Broadcasting/Drivers/SocketBroadcastDriver.php
| Module    : Broadcasting
| Namespace : NetFusion\Core\Broadcasting\Drivers
| Purpose   : Native WebSocket Broadcast Driver for real-time channel delivery.
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
use NetFusion\Core\Broadcasting\Contracts\ChannelInterface;
use NetFusion\Core\Sockets\SocketManager;

/**
 * Class SocketBroadcastDriver
 *
 * Dispatches broadcast events over WebSocket channels via SocketManager IPC push.
 *
 * @package NetFusion\Core\Broadcasting\Drivers
 */
class SocketBroadcastDriver implements BroadcastDriverInterface
{
    public function __construct(
        protected ?SocketManager $socketManager = null
    ) {}

    /**
     * Broadcast the given event and payload to the specified channels.
     *
     * @param array<int, ChannelInterface|string> $channels
     * @param string                              $event
     * @param array<string, mixed>                $payload
     */
    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        $manager = $this->socketManager ?? (function_exists('app') && app()->has(SocketManager::class) ? app(SocketManager::class) : null);
        if (!$manager) {
            return;
        }

        $channelNames = [];
        foreach ($channels as $channel) {
            $channelNames[] = $channel instanceof ChannelInterface ? $channel->name() : (string) $channel;
        }

        $manager->push($channelNames, $event, $payload);
    }
}
