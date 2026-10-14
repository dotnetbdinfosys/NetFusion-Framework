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

interface WindowInterface
{
    /**
     * Get window unique key / identifier.
     */
    public function getKey(): string;

    /**
     * Get start timestamp (inclusive).
     */
    public function getStart(): float;

    /**
     * Get end timestamp (exclusive).
     */
    public function getEnd(): float;

    /**
     * Check if a timestamp falls inside this window.
     */
    public function contains(float $timestamp): bool;

    /**
     * Check if window has expired relative to watermark / current time.
     */
    public function isExpired(float $watermark): bool;

    /**
     * Get human-readable description or debug info.
     */
    public function toString(): string;
}
