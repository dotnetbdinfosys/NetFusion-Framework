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

namespace NetFusion\Core\Actors\Contracts;

use NetFusion\Core\Actors\Core\Envelope;

interface MailboxInterface
{
    /**
     * Enqueue a message envelope.
     */
    public function enqueue(Envelope $envelope): void;

    /**
     * Dequeue the next message envelope, or null if empty.
     */
    public function dequeue(): ?Envelope;

    /**
     * Check if mailbox has pending messages.
     */
    public function hasMessages(): bool;

    /**
     * Number of pending messages in the mailbox.
     */
    public function count(): int;

    /**
     * Clear all pending messages in mailbox.
     */
    public function clear(): void;
}
