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

namespace NetFusion\Core\Auth\WebAuthn\Traits;

use NetFusion\Core\Auth\WebAuthn\Data\WebAuthnCredential;
use NetFusion\Core\Auth\WebAuthn\WebAuthnManager;

trait HasPasskeys
{
    /**
     * Get all passkey credentials for this entity.
     *
     * @return WebAuthnCredential[]
     */
    public function getPasskeys(): array
    {
        $manager = $this->getWebAuthnManager();
        return $manager->getStore()->findByUserId($this->getAuthIdentifier());
    }

    public function hasPasskeys(): bool
    {
        return count($this->getPasskeys()) > 0;
    }

    public function registerPasskey(array $response, string $expectedChallenge, array $metadata = []): WebAuthnCredential
    {
        $metadata['user_id'] = $this->getAuthIdentifier();
        $manager = $this->getWebAuthnManager();
        return $manager->verifyRegistration($response, $expectedChallenge, $metadata);
    }

    public function removePasskey(string $credentialId): bool
    {
        $manager = $this->getWebAuthnManager();
        $cred = $manager->getStore()->findById($credentialId);

        if ($cred !== null && (string)$cred->userId === (string)$this->getAuthIdentifier()) {
            return $manager->getStore()->delete($credentialId);
        }

        return false;
    }

    public function getAuthIdentifier(): string|int
    {
        return $this->id ?? ($this->attributes['id'] ?? 0);
    }

    protected function getWebAuthnManager(): WebAuthnManager
    {
        if (function_exists('app') && app()->has(WebAuthnManager::class)) {
            return app(WebAuthnManager::class);
        }
        return new WebAuthnManager();
    }
}
