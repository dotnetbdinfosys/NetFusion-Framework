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

namespace NetFusion\Core\Auth\WebAuthn\Store;

use NetFusion\Core\Auth\WebAuthn\Contracts\CredentialStoreInterface;
use NetFusion\Core\Auth\WebAuthn\Data\WebAuthnCredential;

class ArrayCredentialStore implements CredentialStoreInterface
{
    /** @var array<string, WebAuthnCredential> */
    protected array $credentials = [];

    public function findById(string $credentialId): ?WebAuthnCredential
    {
        return $this->credentials[$credentialId] ?? null;
    }

    public function findByUserId(string|int $userId): array
    {
        $matches = [];
        foreach ($this->credentials as $cred) {
            if ((string)$cred->userId === (string)$userId) {
                $matches[] = $cred;
            }
        }
        return $matches;
    }

    public function save(WebAuthnCredential $credential): bool
    {
        $this->credentials[$credential->id] = $credential;
        return true;
    }

    public function updateCounter(string $credentialId, int $newCounter): bool
    {
        if (isset($this->credentials[$credentialId])) {
            $this->credentials[$credentialId]->signCount = $newCounter;
            return true;
        }
        return false;
    }

    public function delete(string $credentialId): bool
    {
        if (isset($this->credentials[$credentialId])) {
            unset($this->credentials[$credentialId]);
            return true;
        }
        return false;
    }

    public function all(): array
    {
        return array_values($this->credentials);
    }
}
