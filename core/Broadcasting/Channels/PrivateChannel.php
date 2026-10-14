<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Broadcasting/Channels/PrivateChannel.php
| Module    : Broadcasting
| Namespace : NetFusion\Core\Broadcasting\Channels
| Purpose   : Authenticated private broadcast channel representation.
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
 * Class PrivateChannel
 *
 * Represents an authenticated private broadcast channel.
 *
 * @package NetFusion\Core\Broadcasting\Channels
 */
class PrivateChannel extends Channel
{
    public function __construct(string $name)
    {
        $normalized = str_starts_with($name, 'private-') ? $name : 'private-' . $name;
        parent::__construct($normalized);
    }

    /**
     * {@inheritdoc}
     */
    public function isPrivate(): bool
    {
        return true;
    }
}
