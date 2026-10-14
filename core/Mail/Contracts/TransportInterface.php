<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Mail/Contracts/TransportInterface.php
| Module    : Core\Mail\Contracts
| Namespace : NetFusion\Core\Mail\Contracts
| Purpose   : Contract for underlying mail delivery transport drivers.
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

namespace NetFusion\Core\Mail\Contracts;

use NetFusion\Core\Mail\Message;

/**
 * NetFusion Mail Transport Interface
 *
 * Defines the contract that every mail transport driver must implement.
 *
 * @package NetFusion\Core\Mail\Contracts
 * @version 1.0.0
 */
interface TransportInterface
{
    /**
     * Send the given email message.
     *
     * @param  Message $message
     * @return bool
     */
    public function send(Message $message): bool;
}
