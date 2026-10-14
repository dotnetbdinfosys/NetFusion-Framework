<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Actors\Mailbox;

use NetFusion\Core\Actors\Contracts\MailboxInterface;
use NetFusion\Core\Actors\Core\Envelope;
use SplQueue;

class UnboundedMailbox implements MailboxInterface
{
    protected SplQueue $queue;

    public function __construct()
    {
        $this->queue = new SplQueue();
    }

    public function enqueue(Envelope $envelope): void
    {
        $this->queue->enqueue($envelope);
    }

    public function dequeue(): ?Envelope
    {
        return $this->queue->isEmpty() ? null : $this->queue->dequeue();
    }

    public function hasMessages(): bool
    {
        return !$this->queue->isEmpty();
    }

    public function count(): int
    {
        return $this->queue->count();
    }

    public function clear(): void
    {
        while (!$this->queue->isEmpty()) {
            $this->queue->dequeue();
        }
    }
}
