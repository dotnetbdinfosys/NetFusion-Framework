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

namespace NetFusion\Core\Auth\WebAuthn\Contracts;

use NetFusion\Core\Auth\WebAuthn\Data\WebAuthnCredential;

interface CredentialStoreInterface
{
    /**
     * Find a credential by its base64url or raw credential ID.
     */
    public function findById(string $credentialId): ?WebAuthnCredential;

    /**
     * Get all credentials belonging to a user ID.
     *
     * @return WebAuthnCredential[]
     */
    public function findByUserId(string|int $userId): array;

    /**
     * Save or insert a new credential.
     */
    public function save(WebAuthnCredential $credential): bool;

    /**
     * Update sign counter for clone/replay detection.
     */
    public function updateCounter(string $credentialId, int $newCounter): bool;

    /**
     * Delete a credential by ID.
     */
    public function delete(string $credentialId): bool;
}
