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

namespace NetFusion\Core\CEP\Contracts;

interface WindowAssignerInterface
{
    /**
     * Assign event to one or more windows.
     *
     * @param EventInterface $event
     * @param float $timestamp
     * @return array<WindowInterface>
     */
    public function assignWindows(EventInterface $event, float $timestamp): array;

    /**
     * Determine if window assigner is event-time or processing-time based.
     */
    public function isEventTime(): bool;

    /**
     * Get window assigner type/description.
     */
    public function getType(): string;
}
