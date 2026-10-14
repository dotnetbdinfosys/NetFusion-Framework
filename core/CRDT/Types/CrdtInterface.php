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

namespace NetFusion\Core\CRDT\Types;

use NetFusion\Core\CRDT\Enums\CrdtType;

interface CrdtInterface
{
    /**
     * Unique type identifier for this CRDT.
     */
    public function getType(): CrdtType;

    /**
     * Identifier of the local node owning this instance.
     */
    public function getNodeId(): string;

    /**
     * Compute and return the converged client-facing value.
     */
    public function getValue(): mixed;

    /**
     * Export raw CRDT state for persistence or full state-based synchronization.
     */
    public function getState(): array;

    /**
     * Idempotently and commutatively merge state from a remote replica.
     */
    public function merge(array $remoteState): self;

    /**
     * Generate state delta since a given vector clock or baseline.
     */
    public function delta(?array $sinceVectorClock = null): array;
}
