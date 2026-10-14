<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Broadcasting/Contracts/ChannelInterface.php
| Module    : Broadcasting
| Namespace : NetFusion\Core\Broadcasting\Contracts
| Purpose   : Contract for real-time broadcast channel abstraction.
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
 * Interface ChannelInterface
 *
 * Represents a broadcast transmission channel (public, private, or presence).
 *
 * @package NetFusion\Core\Broadcasting\Contracts
 */
interface ChannelInterface
{
    /**
     * Get the channel name.
     */
    public function getName(): string;

    /**
     * Determine if the channel requires authentication.
     */
    public function isPrivate(): bool;

    /**
     * Determine if the channel tracks participant presence.
     */
    public function isPresence(): bool;

    /**
     * Convert channel instance to string representation.
     */
    public function __toString(): string;
}
