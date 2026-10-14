<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Mail/Transports/ArrayTransport.php
| Module    : Core\Mail\Transports
| Namespace : NetFusion\Core\Mail\Transports
| Purpose   : In-memory mail transport for testing and message assertions.
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

namespace NetFusion\Core\Mail\Transports;

use NetFusion\Core\Mail\Contracts\TransportInterface;
use NetFusion\Core\Mail\Message;

/**
 * NetFusion Array Mail Transport
 *
 * Captures sent messages in memory for unit testing assertions.
 *
 * @package NetFusion\Core\Mail\Transports
 * @version 1.0.0
 */
class ArrayTransport implements TransportInterface
{
    /**
     * Captured messages.
     *
     * @var array<int, Message>
     */
    protected array $messages = [];

    /**
     * {@inheritDoc}
     */
    public function send(Message $message): bool
    {
        $this->messages[] = $message;
        return true;
    }

    /**
     * Retrieve all sent messages.
     *
     * @return array<int, Message>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the count of sent messages.
     */
    public function count(): int
    {
        return count($this->messages);
    }

    /**
     * Get the last sent message, or null.
     */
    public function last(): ?Message
    {
        return !empty($this->messages) ? end($this->messages) : null;
    }

    /**
     * Flush all captured messages.
     */
    public function flush(): void
    {
        $this->messages = [];
    }
}
