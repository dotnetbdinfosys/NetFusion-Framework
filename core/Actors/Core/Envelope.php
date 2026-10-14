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

class Envelope
{
    /**
     * @param mixed                  $message       Payload
     * @param ActorRefInterface|null $sender        Sender actor ref
     * @param int                    $priority      Higher integer means higher priority
     * @param string|null            $correlationId For tracking request-response
     * @param float                  $timestamp
     */
    public function __construct(
        public readonly mixed $message,
        public readonly ?ActorRefInterface $sender = null,
        public readonly int $priority = 0,
        public readonly ?string $correlationId = null,
        public readonly float $timestamp = 0.0
    ) {}

    public static function create(
        mixed $message,
        ?ActorRefInterface $sender = null,
        int $priority = 0,
        ?string $correlationId = null
    ): self {
        return new self(
            message: $message,
            sender: $sender,
            priority: $priority,
            correlationId: $correlationId,
            timestamp: microtime(true)
        );
    }
}
