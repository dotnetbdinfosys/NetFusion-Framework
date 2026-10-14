<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Broadcasting/Channels/Channel.php
| Module    : Broadcasting
| Namespace : NetFusion\Core\Broadcasting\Channels
| Purpose   : Public broadcast channel representation.
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

use NetFusion\Core\Broadcasting\Contracts\ChannelInterface;

/**
 * Class Channel
 *
 * Represents an unauthenticated public broadcast channel.
 *
 * @package NetFusion\Core\Broadcasting\Channels
 */
class Channel implements ChannelInterface
{
    /**
     * @param string $name Channel identifier.
     */
    public function __construct(
        protected string $name
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function isPrivate(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isPresence(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return $this->getName();
    }
}
