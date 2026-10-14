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

namespace NetFusion\Core\Actors\Core;

use NetFusion\Core\Actors\Contracts\ActorRefInterface;

class DeadLetter
{
    /**
     * @param mixed                  $message
     * @param ActorRefInterface|null $sender
     * @param ActorRefInterface|null $recipient
     * @param string                 $reason
     * @param float                  $timestamp
     */
    public function __construct(
        public readonly mixed $message,
        public readonly ?ActorRefInterface $sender,
        public readonly ?ActorRefInterface $recipient,
        public readonly string $reason = 'Recipient terminated or mailbox full',
        public readonly float $timestamp = 0.0
    ) {}

    public static function create(
        mixed $message,
        ?ActorRefInterface $sender,
        ?ActorRefInterface $recipient,
        string $reason = 'Recipient terminated or mailbox full'
    ): self {
        return new self(
            message: $message,
            sender: $sender,
            recipient: $recipient,
            reason: $reason,
            timestamp: microtime(true)
        );
    }
}
