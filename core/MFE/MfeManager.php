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

namespace NetFusion\Core\MFE;

use NetFusion\Core\MFE\Enums\RenderMode;
use NetFusion\Core\MFE\EventBus\CrossMfeEventBus;
use NetFusion\Core\MFE\Federation\ModuleFederationRegistry;
use NetFusion\Core\MFE\Models\MfeFragment;
use NetFusion\Core\MFE\Models\RemoteContainer;
use NetFusion\Core\MFE\SSR\FragmentStitcher;

/**
 * Master Enterprise Micro-Frontend & Module Federation Facade (Webpack 5 Architecture).
 * Unifies Remote Container Registry, Shared Scope Resolution, Edge SSR Fragment Stitching,
 * and Cross-MFE Reactive Event Bus.
 */
class MfeManager
{
    /** @var array<string, mixed> */
    protected array $config;
    protected ModuleFederationRegistry $registry;
    protected FragmentStitcher $stitcher;
    protected CrossMfeEventBus $eventBus;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->registry = new ModuleFederationRegistry();
        $this->stitcher = new FragmentStitcher($this->registry);
        $this->eventBus = new CrossMfeEventBus();

        // Register default configured remotes if present
        if (!empty($config['remotes'])) {
            foreach ($config['remotes'] as $name => $remoteDef) {
                $this->registerRemote(
                    $name,
                    $remoteDef['entry'] ?? '',
                    $remoteDef['manifest'] ?? '',
                    $remoteDef['exposes'] ?? [],
                    $remoteDef['shared'] ?? [],
                    (int)($remoteDef['timeout'] ?? 3000),
                    $remoteDef['fallback'] ?? null
                );
            }
        }
    }

    public function getRegistry(): ModuleFederationRegistry
    {
        return $this->registry;
    }

    public function getStitcher(): FragmentStitcher
    {
        return $this->stitcher;
    }

    public function getEventBus(): CrossMfeEventBus
    {
        return $this->eventBus;
    }

    /**
     * Register a new remote Micro-Frontend container.
     */
    public function registerRemote(
        string $name,
        string $entryUrl,
        string $manifestUrl = '',
        array $exposes = [],
        array $shared = [],
        int $timeout = 3000,
        ?string $fallbackHtml = null
    ): RemoteContainer {
        $container = new RemoteContainer($name, $entryUrl, $manifestUrl, $exposes, $shared, $timeout, $fallbackHtml);
        $this->registry->registerRemote($container);
        return $container;
    }

    /**
     * Render a single fragment.
     */
    public function fragment(
        string $remote,
        string $component,
        array $props = [],
        RenderMode $renderMode = RenderMode::SSR,
        ?string $fallbackHtml = null
    ): string {
        $frag = new MfeFragment($remote, $component, $props, $renderMode, $fallbackHtml);
        return $this->stitcher->renderFragment($frag);
    }

    /**
     * Stitch an HTML page shell containing <mfe-fragment ... /> elements.
     */
    public function render(string $templateHtml, array $context = []): string
    {
        return $this->stitcher->stitch($templateHtml, $context);
    }

    /**
     * Generate `mf-manifest.json` for host application.
     *
     * @return array<string, mixed>
     */
    public function manifest(string $name = 'host'): array
    {
        return $this->registry->generateManifest($name);
    }

    /**
     * Dispatch an event on the Cross-MFE Bus.
     */
    public function dispatch(string $event, mixed $payload = null, string $origin = 'host'): int
    {
        return $this->eventBus->dispatch($event, $payload, $origin);
    }

    /**
     * Subscribe to an event on the Cross-MFE Bus.
     */
    public function on(string $event, callable $listener): self
    {
        $this->eventBus->on($event, $listener);
        return $this;
    }

    /**
     * Set a shared state key across all Micro-Frontends.
     */
    public function setState(string $key, mixed $value, string $origin = 'host'): self
    {
        $this->eventBus->setState($key, $value, $origin);
        return $this;
    }

    public function getState(string $key, mixed $default = null): mixed
    {
        return $this->eventBus->getState($key, $default);
    }
}
