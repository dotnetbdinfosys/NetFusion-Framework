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

interface SourceInterface
{
    /**
     * Get source connector name.
     */
    public function getName(): string;

    /**
     * Ingest or poll next batch of events.
     *
     * @return array<EventInterface>
     */
    public function poll(): array;

    /**
     * Push an event into this source manually/programmatically.
     */
    public function push(EventInterface $event): void;

    /**
     * Check if source has more events available.
     */
    public function hasNext(): bool;
}
