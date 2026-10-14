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

namespace NetFusion\Core\Data\Crdt\Contracts;

use JsonSerializable;

interface CrdtInterface extends JsonSerializable
{
    /**
     * Get the resolved, materialized value.
     */
    public function getValue(): mixed;

    /**
     * Merge with another replica's state (Commutative, Associative, Idempotent).
     */
    public function merge(CrdtInterface $other): static;

    /**
     * Export raw internal state for wire transmission.
     */
    public function getState(): array;

    /**
     * Rehydrate instance from raw state array.
     */
    public static function fromState(array $state): static;
}
