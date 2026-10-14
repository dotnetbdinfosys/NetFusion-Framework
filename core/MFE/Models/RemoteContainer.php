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

namespace NetFusion\Core\MFE\Models;

/**
 * Definition of a remote Micro-Frontend container.
 * Corresponds to Webpack 5 Module Federation remote configuration.
 */
class RemoteContainer
{
    protected string $name;
    protected string $entryUrl;
    protected string $manifestUrl;
    /** @var array<string, string> Exposes mapping: [exposedPath => localModule] e.g. ['./Header' => './src/components/Header.vue'] */
    protected array $exposes = [];
    /** @var array<string, array{version: string, singleton?: bool, requiredVersion?: string}> Shared dependencies */
    protected array $shared = [];
    protected int $timeout; // ms
    protected ?string $fallbackHtml;

    /**
     * @param array<string, string> $exposes
     * @param array<string, array{version: string, singleton?: bool, requiredVersion?: string}> $shared
     */
    public function __construct(
        string $name,
        string $entryUrl,
        string $manifestUrl = '',
        array $exposes = [],
        array $shared = [],
        int $timeout = 3000,
        ?string $fallbackHtml = null
    ) {
        $this->name = $name;
        $this->entryUrl = rtrim($entryUrl, '/');
        $this->manifestUrl = $manifestUrl !== '' ? $manifestUrl : ($this->entryUrl . '/mf-manifest.json');
        $this->exposes = $exposes;
        $this->shared = $shared;
        $this->timeout = $timeout;
        $this->fallbackHtml = $fallbackHtml;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEntryUrl(): string
    {
        return $this->entryUrl;
    }

    public function getManifestUrl(): string
    {
        return $this->manifestUrl;
    }

    /**
     * @return array<string, string>
     */
    public function getExposes(): array
    {
        return $this->exposes;
    }

    public function hasExposed(string $module): bool
    {
        return isset($this->exposes[$module]);
    }

    /**
     * @return array<string, array{version: string, singleton?: bool, requiredVersion?: string}>
     */
    public function getShared(): array
    {
        return $this->shared;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getFallbackHtml(): ?string
    {
        return $this->fallbackHtml;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'entry' => $this->entryUrl,
            'manifest' => $this->manifestUrl,
            'exposes' => $this->exposes,
            'shared' => $this->shared,
            'timeout' => $this->timeout,
        ];
    }
}
