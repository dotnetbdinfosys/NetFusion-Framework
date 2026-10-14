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

namespace NetFusion\Core\Security\Vault;

use RuntimeException;
use NetFusion\Core\Security\Vault\Contracts\VaultStoreInterface;
use NetFusion\Core\Security\Vault\Encryption\Contracts\KeyProviderInterface;
use NetFusion\Core\Security\Vault\Encryption\EnvelopeEncrypter;
use NetFusion\Core\Security\Vault\Encryption\LocalKeyProvider;
use NetFusion\Core\Security\Vault\Store\ArrayVaultStore;
use NetFusion\Core\Security\Vault\Store\DatabaseVaultStore;

class VaultManager
{
    protected VaultStoreInterface $store;
    protected EnvelopeEncrypter $encrypter;
    protected KeyProviderInterface $keyProvider;

    public function __construct(
        ?VaultStoreInterface $store = null,
        ?KeyProviderInterface $keyProvider = null,
        ?EnvelopeEncrypter $encrypter = null
    ) {
        $this->keyProvider = $keyProvider ?? new LocalKeyProvider();
        $this->encrypter = $encrypter ?? new EnvelopeEncrypter($this->keyProvider);
        $this->store = $store ?? $this->resolveDefaultStore();
    }

    public function getStore(): VaultStoreInterface
    {
        return $this->store;
    }

    public function setStore(VaultStoreInterface $store): self
    {
        $this->store = $store;
        return $this;
    }

    public function getEncrypter(): EnvelopeEncrypter
    {
        return $this->encrypter;
    }

    public function getKeyProvider(): KeyProviderInterface
    {
        return $this->keyProvider;
    }

    /**
     * Read and decrypt a secret value by path.
     *
     * @return string|array<string, mixed>|null
     */
    public function get(string $path, ?int $version = null): string|array|null
    {
        $secret = $this->store->get($path, $version);
        if (!$secret) {
            return null;
        }

        $decrypted = $this->encrypter->decrypt($secret->payload);
        $json = json_decode($decrypted, true);

        return is_array($json) ? $json : $decrypted;
    }

    public function getSecret(string $path, ?int $version = null): ?Secret
    {
        return $this->store->get($path, $version);
    }

    /**
     * Envelope-encrypt and store a secret value.
     */
    public function set(string $path, string|array $value, array $metadata = []): Secret
    {
        $plaintext = is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : (string)$value;
        $activeVersion = $this->keyProvider->getActiveVersion();

        // Check if previous secret version exists
        $prev = $this->store->get($path);
        $newVersion = $prev ? ($prev->version + 1) : 1;

        $encryptedPayload = $this->encrypter->encrypt($plaintext, $activeVersion);

        $secret = new Secret(
            path: $path,
            version: $newVersion,
            payload: $encryptedPayload,
            metadata: $metadata,
            createdAt: date('Y-m-d H:i:s'),
            updatedAt: date('Y-m-d H:i:s')
        );

        $this->store->set($secret);
        return $secret;
    }

    public function delete(string $path, ?int $version = null): bool
    {
        return $this->store->delete($path, $version);
    }

    /**
     * List all secrets matching a prefix.
     *
     * @return string[]
     */
    public function list(string $prefix = ''): array
    {
        return $this->store->list($prefix);
    }

    /**
     * Issue a dynamic ephemeral lease for short-lived credentials.
     */
    public function lease(string $path, int $ttlSeconds = 3600, array $options = []): SecretLease
    {
        $creds = DynamicSecretGenerator::generate($path, $options);
        $credsJson = json_encode($creds, JSON_UNESCAPED_SLASHES);

        $leaseId = bin2hex(random_bytes(16));
        $activeVersion = $this->keyProvider->getActiveVersion();
        $encrypted = $this->encrypter->encrypt($credsJson, $activeVersion);

        $lease = new SecretLease(
            id: $leaseId,
            path: $path,
            encryptedCredentials: $encrypted,
            expiresAt: microtime(true) + $ttlSeconds,
            ttl: $ttlSeconds,
            renewable: (bool)($options['renewable'] ?? true)
        );

        $this->store->saveLease($lease);
        return $lease;
    }

    /**
     * Get and decrypt lease credentials.
     *
     * @return array<string, mixed>|null
     */
    public function getLeaseCredentials(string $leaseId): ?array
    {
        $lease = $this->store->getLease($leaseId);
        if (!$lease || $lease->isExpired()) {
            return null;
        }

        $decrypted = $this->encrypter->decrypt($lease->encryptedCredentials);
        return json_decode($decrypted, true) ?: [];
    }

    public function renewLease(string $leaseId, int $incrementSeconds = 3600): ?SecretLease
    {
        $lease = $this->store->getLease($leaseId);
        if (!$lease || $lease->isExpired()) {
            return null;
        }

        $lease->renew($incrementSeconds);
        $this->store->saveLease($lease);
        return $lease;
    }

    public function revokeLease(string $leaseId): bool
    {
        return $this->store->revokeLease($leaseId);
    }

    /**
     * Rotate active Master Key (KEK) to a new version.
     *
     * @return int New KEK version.
     */
    public function rotateMasterKey(): int
    {
        return $this->keyProvider->rotateMasterKey();
    }

    /**
     * Re-wrap all stored secrets with the active KEK version (zero-downtime rotation).
     *
     * @return int Count of re-encrypted secrets.
     */
    public function reencryptAll(): int
    {
        $activeKek = $this->keyProvider->getActiveVersion();
        $secrets = $this->store->all();
        $count = 0;

        foreach ($secrets as $secret) {
            if ($secret->payload->version !== $activeKek) {
                $rewrappedPayload = $this->encrypter->rewrap($secret->payload, $activeKek);
                $updatedSecret = new Secret(
                    path: $secret->path,
                    version: $secret->version,
                    payload: $rewrappedPayload,
                    metadata: $secret->metadata,
                    createdAt: $secret->createdAt,
                    updatedAt: date('Y-m-d H:i:s')
                );
                $this->store->set($updatedSecret);
                $count++;
            }
        }

        return $count;
    }

    protected function resolveDefaultStore(): VaultStoreInterface
    {
        if (function_exists('app') && app()->has('db')) {
            try {
                return new DatabaseVaultStore(app('db'));
            } catch (\Throwable) {
                // Fallback to array store
            }
        }

        return new ArrayVaultStore();
    }
}
