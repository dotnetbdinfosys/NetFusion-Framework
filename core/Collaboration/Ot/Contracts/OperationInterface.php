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

namespace NetFusion\Core\Collaboration\Ot\Contracts;

use JsonSerializable;

interface OperationInterface extends JsonSerializable
{
    /**
     * Apply this operation to a string document and return the transformed text.
     */
    public function apply(string $document): string;

    /**
     * Transform this operation against another concurrent operation.
     * Returns a 2-tuple: [transformedThis, transformedOther].
     *
     * @return array{0: OperationInterface, 1: OperationInterface}
     */
    public function transform(OperationInterface $other, string $side = 'left'): array;

    /**
     * Compose this operation with a subsequent operation to create a single atomic operation.
     */
    public function compose(OperationInterface $other): OperationInterface;

    /**
     * Invert this operation relative to a base document state.
     */
    public function invert(string $document): OperationInterface;

    /**
     * Check if the operation is a no-op (makes no changes).
     */
    public function isNoop(): bool;

    /**
     * Convert operation to an array structure.
     */
    public function toArray(): array;

    /**
     * Rehydrate an operation from an array structure.
     */
    public static function fromArray(array $data): static;
}
