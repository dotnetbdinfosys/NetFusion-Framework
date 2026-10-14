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

namespace NetFusion\Core\Security\Vault\Contracts;

use NetFusion\Core\Security\Vault\Secret;
use NetFusion\Core\Security\Vault\SecretLease;

interface VaultStoreInterface
{
    /**
     * Get secret by path and optional version.
     */
    public function get(string $path, ?int $version = null): ?Secret;

    /**
     * Store a secret.
     */
    public function set(Secret $secret): bool;

    /**
     * Delete a secret path (all versions or specific).
     */
    public function delete(string $path, ?int $version = null): bool;

    /**
     * List all secret paths under a prefix.
     *
     * @return string[]
     */
    public function list(string $prefix = ''): array;

    /**
     * Get all active secrets for re-encryption / rotation.
     *
     * @return Secret[]
     */
    public function all(): array;

    /**
     * Save dynamic secret lease.
     */
    public function saveLease(SecretLease $lease): bool;

    /**
     * Get dynamic secret lease by ID.
     */
    public function getLease(string $leaseId): ?SecretLease;

    /**
     * Revoke dynamic secret lease.
     */
    public function revokeLease(string $leaseId): bool;

    /**
     * Prune expired leases.
     */
    public function pruneExpiredLeases(): int;
}
