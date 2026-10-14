<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Broadcasting/Contracts/ShouldBroadcast.php
| Module    : Broadcasting
| Namespace : NetFusion\Core\Broadcasting\Contracts
| Purpose   : Interface for events that should be broadcasted over real-time channels.
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
 * Interface ShouldBroadcast
 *
 * Marks an application event to be automatically broadcasted across real-time channels.
 *
 * @package NetFusion\Core\Broadcasting\Contracts
 */
interface ShouldBroadcast
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, ChannelInterface|string>
     */
    public function broadcastOn(): array;

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): ?string;

    /**
     * Get the data that should be broadcasted with the event.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array;
}
