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

interface ActorRefInterface
{
    /**
     * Get the hierarchical actor path (e.g. netfusion://local/user/order_service).
     */
    public function path(): string;

    /**
     * Get short actor name.
     */
    public function name(): string;

    /**
     * Asynchronously send a message to the actor (Fire-and-Forget).
     *
     * @param mixed                  $message
     * @param ActorRefInterface|null $sender
     */
    public function tell(mixed $message, ?ActorRefInterface $sender = null): void;

    /**
     * Send a message and wait synchronously or via Future for the response.
     *
     * @param mixed $message
     * @param float $timeout
     * @return mixed
     */
    public function ask(mixed $message, float $timeout = 5.0): mixed;

    /**
     * Check if the actor is alive and active.
     */
    public function isAlive(): bool;
}
