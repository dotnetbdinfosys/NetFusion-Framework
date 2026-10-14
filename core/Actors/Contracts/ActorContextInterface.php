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

interface ActorContextInterface
{
    /**
     * Get reference to self actor.
     */
    public function self(): ActorRefInterface;

    /**
     * Get reference to parent actor, or null if root.
     */
    public function parent(): ?ActorRefInterface;

    /**
     * Get sender of currently processed message.
     */
    public function sender(): ?ActorRefInterface;

    /**
     * Spawn a child actor.
     *
     * @param class-string<ActorInterface> $actorClass
     * @param string                       $name
     * @param array<string, mixed>         $args
     * @return ActorRefInterface
     */
    public function actorOf(string $actorClass, string $name = '', array $args = []): ActorRefInterface;

    /**
     * Get a child actor reference by name.
     */
    public function child(string $name): ?ActorRefInterface;

    /**
     * Get all active child actor references.
     *
     * @return list<ActorRefInterface>
     */
    public function children(): array;

    /**
     * Stop a child actor.
     */
    public function stop(ActorRefInterface $child): void;

    /**
     * Switch actor message receive behavior.
     */
    public function become(callable $behavior): void;

    /**
     * Revert to previous receive behavior.
     */
    public function unbecome(): void;
}
