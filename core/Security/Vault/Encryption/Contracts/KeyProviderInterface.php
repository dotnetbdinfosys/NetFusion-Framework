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

namespace NetFusion\Core\Security\Vault\Encryption\Contracts;

interface KeyProviderInterface
{
    /**
     * Get Master Key (Key Encryption Key - KEK) for a given version.
     */
    public function getMasterKey(int $version): string;

    /**
     * Get the active KEK version number.
     */
    public function getActiveVersion(): int;

    /**
     * Rotate and generate a new KEK version.
     *
     * @return int The new active KEK version.
     */
    public function rotateMasterKey(): int;

    /**
     * Check if a specific KEK version exists.
     */
    public function hasVersion(int $version): bool;
}
