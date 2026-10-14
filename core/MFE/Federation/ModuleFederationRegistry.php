<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\MFE\Federation;

use NetFusion\Core\MFE\Exceptions\RemoteContainerNotFoundException;
use NetFusion\Core\MFE\Models\RemoteContainer;

/**
 * Module Federation Manifest Registry & Shared Scope Version Resolver (Webpack 5 Standard).
 * Generates and validates `mf-manifest.json` schemas, resolves semver compatibility in sharedScope,
 * and maintains remote container catalogs.
 */
class ModuleFederationRegistry
{
    /** @var array<string, RemoteContainer> [remoteName => RemoteContainer] */
    protected array $remotes = [];
    /** @var array<string, array<string, array{version: string, from: string, singleton: bool}>> [pkgName => [version => info]] */
    protected array $sharedScope = [];

    /**
     * Register a remote container.
     */
    public function registerRemote(RemoteContainer $container): self
    {
        $this->remotes[$container->getName()] = $container;

        // Register shared scope dependencies declared by remote
        foreach ($container->getShared() as $pkg => $cfg) {
            $this->registerShared($pkg, $cfg['version'] ?? '1.0.0', $container->getName(), (bool)($cfg['singleton'] ?? false));
        }

        return $this;
    }

    public function getRemote(string $name): ?RemoteContainer
    {
        return $this->remotes[$name] ?? null;
    }

    public function hasRemote(string $name): bool
    {
        return isset($this->remotes[$name]);
    }

    public function registerContainer(RemoteContainer $container): self
    {
        return $this->registerRemote($container);
    }

    public function getContainer(string $name): ?RemoteContainer
    {
        return $this->getRemote($name);
    }

    public function hasContainer(string $name): bool
    {
        return $this->hasRemote($name);
    }

    public function resolveSharedDependency(string $packageName, ?string $requiredVersion = null): ?array
    {
        return $this->resolveShared($packageName, $requiredVersion);
    }

    /**
     * @return array<string, RemoteContainer>
     */
    public function getRemotes(): array
    {
        return $this->remotes;
    }

    /**
     * Register a shared library dependency in sharedScope.
     */
    public function registerShared(string $packageName, string $version, string $from = 'host', bool $singleton = false): self
    {
        if (!isset($this->sharedScope[$packageName])) {
            $this->sharedScope[$packageName] = [];
        }

        $this->sharedScope[$packageName][$version] = [
            'version' => $version,
            'from' => $from,
            'singleton' => $singleton,
        ];

        return $this;
    }

    /**
     * Resolve the most compatible version for a package in sharedScope (Semver compliant).
     * If singleton=true, picks the highest version available.
     */
    public function resolveShared(string $packageName, ?string $requiredVersion = null): ?array
    {
        if (!isset($this->sharedScope[$packageName]) || empty($this->sharedScope[$packageName])) {
            return null;
        }

        $versions = $this->sharedScope[$packageName];

        // Sort versions descending (e.g. 18.2.0 > 18.0.0 > 17.0.2)
        uksort($versions, 'version_compare');
        $reversed = array_reverse($versions, true);

        if ($requiredVersion === null || $requiredVersion === '*' || $requiredVersion === '') {
            return reset($reversed);
        }

        // Exact or semver range check
        foreach ($reversed as $ver => $info) {
            if ($this->satisfiesSemver($ver, $requiredVersion)) {
                return $info;
            }
        }

        // Fallback to highest available if singleton
        $highest = reset($reversed);
        if ($highest['singleton']) {
            return $highest;
        }

        return null;
    }

    /**
     * Basic Semver compatibility checker (~, ^, or exact).
     */
    protected function satisfiesSemver(string $version, string $range): bool
    {
        if ($range === '*' || $range === $version) {
            return true;
        }

        if (str_starts_with($range, '^')) {
            $base = substr($range, 1);
            $baseMajor = explode('.', $base)[0];
            $verMajor = explode('.', $version)[0];
            return $baseMajor === $verMajor && version_compare($version, $base, '>=');
        }

        if (str_starts_with($range, '~')) {
            $base = substr($range, 1);
            $baseParts = explode('.', $base);
            $verParts = explode('.', $version);
            return ($baseParts[0] ?? '') === ($verParts[0] ?? '')
                && ($baseParts[1] ?? '') === ($verParts[1] ?? '')
                && version_compare($version, $base, '>=');
        }

        return version_compare($version, $range, '>=');
    }

    /**
     * Generate standard Webpack 5 `mf-manifest.json` schema.
     *
     * @return array<string, mixed>
     */
    public function generateManifest(string $name = 'host'): array
    {
        $remotesMap = [];
        foreach ($this->remotes as $rName => $remote) {
            $remotesMap[$rName] = [
                'entry' => $remote->getEntryUrl(),
                'manifest' => $remote->getManifestUrl(),
                'exposes' => $remote->getExposes(),
            ];
        }

        $sharedMap = [];
        foreach ($this->sharedScope as $pkg => $versions) {
            $sharedMap[$pkg] = array_values($versions);
        }

        return [
            'name' => $name,
            'type' => 'container',
            'remotes' => $remotesMap,
            'shared' => $sharedMap,
            'timestamp' => time(),
        ];
    }
}
