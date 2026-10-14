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
use SplPriorityQueue;

class PriorityMailbox implements MailboxInterface
{
    protected SplPriorityQueue $queue;
    protected int $serial = 0;

    public function __construct()
    {
        $this->queue = new SplPriorityQueue();
        $this->queue->setExtractFlags(SplPriorityQueue::EXTR_DATA);
    }

    public function enqueue(Envelope $envelope): void
    {
        // Composite priority: [priority, -serial] to preserve FIFO among equal priority
        $this->serial++;
        $priorityTuple = [$envelope->priority, -$this->serial];
        $this->queue->insert($envelope, $priorityTuple);
    }

    public function dequeue(): ?Envelope
    {
        return $this->queue->isEmpty() ? null : $this->queue->extract();
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
            $this->queue->extract();
        }
    }
}
