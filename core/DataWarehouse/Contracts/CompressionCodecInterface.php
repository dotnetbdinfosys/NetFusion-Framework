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

namespace NetFusion\Core\DataWarehouse\Contracts;

interface CompressionCodecInterface
{
    /**
     * Get unique codec name/identifier.
     */
    public function getName(): string;

    /**
     * Compress an array of raw values into a compressed binary or structure.
     *
     * @param array<int, mixed> $values
     * @return mixed
     */
    public function compress(array $values): mixed;

    /**
     * Decompress compressed payload back to original array of values.
     *
     * @param mixed $compressed
     * @return array<int, mixed>
     */
    public function decompress(mixed $compressed): array;
}
