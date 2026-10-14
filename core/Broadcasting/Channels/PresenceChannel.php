<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Broadcasting/Channels/PresenceChannel.php
| Module    : Broadcasting
| Namespace : NetFusion\Core\Broadcasting\Channels
| Purpose   : Authenticated presence broadcast channel with participant tracking.
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

namespace NetFusion\Core\Broadcasting\Channels;

/**
 * Class PresenceChannel
 *
 * Represents an authenticated channel tracking participant presence and awareness.
 *
 * @package NetFusion\Core\Broadcasting\Channels
 */
class PresenceChannel extends PrivateChannel
{
    public function __construct(string $name)
    {
        $normalized = str_starts_with($name, 'presence-') ? $name : 'presence-' . $name;
        // Skip PrivateChannel's 'private-' prefixing
        Channel::__construct($normalized);
    }

    /**
     * {@inheritdoc}
     */
    public function isPresence(): bool
    {
        return true;
    }
}
