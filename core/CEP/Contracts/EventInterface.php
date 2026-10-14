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

interface EventInterface
{
    /**
     * Get unique event identifier.
     */
    public function getId(): string;

    /**
     * Get event type / stream topic name.
     */
    public function getType(): string;

    /**
     * Get event timestamp (epoch timestamp in seconds with microsecond precision).
     */
    public function getTimestamp(): float;

    /**
     * Get entire event payload.
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array;

    /**
     * Get specific payload attribute.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Check if payload has specific attribute.
     */
    public function has(string $key): bool;

    /**
     * Get metadata/headers.
     *
     * @return array<string, mixed>
     */
    public function getHeaders(): array;

    /**
     * Get specific header.
     */
    public function getHeader(string $name, mixed $default = null): mixed;

    /**
     * Convert event to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
