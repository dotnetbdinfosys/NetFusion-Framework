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

namespace NetFusion\Core\Security\Vault\Store;

use NetFusion\Core\Security\Vault\Contracts\VaultStoreInterface;
use NetFusion\Core\Security\Vault\Secret;
use NetFusion\Core\Security\Vault\SecretLease;

class ArrayVaultStore implements VaultStoreInterface
{
    /** @var array<string, array<int, Secret>> Path => [version => Secret] */
    protected array $secrets = [];

    /** @var array<string, SecretLease> LeaseId => SecretLease */
    protected array $leases = [];

    public function get(string $path, ?int $version = null): ?Secret
    {
        if (!isset($this->secrets[$path])) {
            return null;
        }

        if ($version !== null) {
            return $this->secrets[$path][$version] ?? null;
        }

        // Return latest version
        $versions = $this->secrets[$path];
        $latestVer = max(array_keys($versions));
        return $versions[$latestVer];
    }

    public function set(Secret $secret): bool
    {
        if (!isset($this->secrets[$secret->path])) {
            $this->secrets[$secret->path] = [];
        }

        $this->secrets[$secret->path][$secret->version] = $secret;
        return true;
    }

    public function delete(string $path, ?int $version = null): bool
    {
        if (!isset($this->secrets[$path])) {
            return false;
        }

        if ($version !== null) {
            unset($this->secrets[$path][$version]);
            if (empty($this->secrets[$path])) {
                unset($this->secrets[$path]);
            }
        } else {
            unset($this->secrets[$path]);
        }

        return true;
    }

    public function list(string $prefix = ''): array
    {
        $keys = array_keys($this->secrets);
        if (empty($prefix)) {
            return $keys;
        }

        return array_values(
            array_filter($keys, fn(string $k) => str_starts_with($k, $prefix))
        );
    }

    public function all(): array
    {
        $all = [];
        foreach ($this->secrets as $pathVersions) {
            foreach ($pathVersions as $s) {
                $all[] = $s;
            }
        }
        return $all;
    }

    public function saveLease(SecretLease $lease): bool
    {
        $this->leases[$lease->id] = $lease;
        return true;
    }

    public function getLease(string $leaseId): ?SecretLease
    {
        return $this->leases[$leaseId] ?? null;
    }

    public function revokeLease(string $leaseId): bool
    {
        if (!isset($this->leases[$leaseId])) {
            return false;
        }

        $this->leases[$leaseId]->revoke();
        return true;
    }

    public function pruneExpiredLeases(): int
    {
        $pruned = 0;
        $now = microtime(true);

        foreach ($this->leases as $id => $lease) {
            if ($lease->isExpired()) {
                unset($this->leases[$id]);
                $pruned++;
            }
        }

        return $pruned;
    }
}
