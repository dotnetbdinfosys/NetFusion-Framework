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

use Throwable;

interface ActorInterface
{
    /**
     * Inject the actor runtime context.
     */
    public function setContext(ActorContextInterface $context): void;

    /**
     * Get the actor runtime context.
     */
    public function getContext(): ActorContextInterface;

    /**
     * Process incoming message.
     *
     * @param mixed $message
     * @return mixed Optional response value for ask() requests
     */
    public function receive(mixed $message): mixed;

    /**
     * Lifecycle Hook: Called immediately after the actor starts.
     */
    public function preStart(): void;

    /**
     * Lifecycle Hook: Called immediately after the actor is stopped.
     */
    public function postStop(): void;

    /**
     * Lifecycle Hook: Called before restarting when an exception occurred.
     */
    public function preRestart(Throwable $reason): void;

    /**
     * Lifecycle Hook: Called after actor is recreated upon restart.
     */
    public function postRestart(Throwable $reason): void;
}
