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

namespace NetFusion\Core\RuleEngine\Contracts;

use JsonSerializable;

interface FactInterface extends JsonSerializable
{
    /**
     * Get unique identifier for the fact instance.
     */
    public function getId(): string;

    /**
     * Get the type/category name of the fact (e.g., 'Order', 'Customer', 'Account').
     */
    public function getType(): string;

    /**
     * Get all fact attributes/payload.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array;

    /**
     * Get a specific attribute value with optional default.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Check if fact has a specific attribute.
     */
    public function has(string $key): bool;

    /**
     * Get cryptographic or unique state hash for fact deduplication/indexing.
     */
    public function getHash(): string;

    /**
     * Return a new fact instance with modified attributes.
     *
     * @param array<string, mixed> $attributes
     */
    public function with(array $attributes): self;
}
