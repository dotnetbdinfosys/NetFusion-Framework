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

namespace NetFusion\Core\SDUI;

use NetFusion\Core\SDUI\Components\CustomComponent;
use NetFusion\Core\SDUI\Exceptions\ComponentValidationException;
use NetFusion\Core\SDUI\Exceptions\SDUIException;
use NetFusion\Core\SDUI\Hydration\HydrationPayload;
use NetFusion\Core\SDUI\Hydration\PatchDiffer;
use NetFusion\Core\SDUI\Hydration\StreamPusher;
use NetFusion\Core\SDUI\Layout\LayoutEngine;
use NetFusion\Core\SDUI\Layout\Screen;
use NetFusion\Core\SDUI\Layout\ThemeContext;
use NetFusion\Core\SDUI\Schema\ComponentInterface;

/**
 * Master Enterprise Server-Driven UI (SDUI) Manager & Facade.
 */
class SDUIManager
{
    protected LayoutEngine $engine;

    /** @var array<string, callable> */
    protected array $customComponentFactories = [];

    /** @var array<string, mixed> */
    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->engine = new LayoutEngine();
    }

    public function getEngine(): LayoutEngine
    {
        return $this->engine;
    }

    /**
     * Register a screen template.
     */
    public function registerScreen(string $screenId, Screen|callable $screen): self
    {
        $this->engine->registerScreen($screenId, $screen);
        return $this;
    }

    /**
     * Create a new Screen layout builder.
     */
    public function screen(string $screenId, string $title = '', ?ThemeContext $theme = null): Screen
    {
        $screen = Screen::make($screenId, $title, $theme);
        $this->registerScreen($screenId, $screen);
        return $screen;
    }

    /**
     * Register a custom component factory.
     */
    public function registerComponent(string $name, callable $factory): self
    {
        $this->customComponentFactories[$name] = $factory;
        return $this;
    }

    /**
     * Instantiate a custom registered or dynamic component.
     */
    public function customComponent(string $name, array $props = [], ?string $id = null): ComponentInterface
    {
        if (isset($this->customComponentFactories[$name])) {
            return ($this->customComponentFactories[$name])($props, $id);
        }

        return CustomComponent::make($name, $props, $id);
    }

    /**
     * Render screen to schema array with contextual filtering.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function render(string $screenId, array $context = []): array
    {
        return $this->engine->render($screenId, $context);
    }

    /**
     * Generate complete client hydration payload.
     *
     * @param array<string, mixed> $context
     */
    public function hydrate(string $screenId, array $context = []): HydrationPayload
    {
        $rendered = $this->render($screenId, $context);
        $secret = $this->config['app_secret'] ?? null;
        return HydrationPayload::create($rendered, $context, $secret);
    }

    /**
     * Compute RFC 6902 JSON patch differences between two schema trees.
     *
     * @param array<string, mixed> $oldTree
     * @param array<string, mixed> $newTree
     * @return array<int, array{op: string, path: string, value?: mixed}>
     */
    public function diff(array $oldTree, array $newTree): array
    {
        return PatchDiffer::diff($oldTree, $newTree);
    }

    /**
     * Apply JSON patches to a target schema array.
     *
     * @param array<string, mixed> $target
     * @param array<int, array{op: string, path: string, value?: mixed}> $patches
     * @return array<string, mixed>
     */
    public function applyPatch(array $target, array $patches): array
    {
        return PatchDiffer::apply($target, $patches);
    }

    /**
     * Generate an SSE stream packet containing delta JSON patches.
     *
     * @param array<string, mixed> $oldTree
     * @param array<string, mixed> $newTree
     */
    public function streamPatch(string $screenId, array $oldTree, array $newTree, ?string $eventId = null): string
    {
        $patches = $this->diff($oldTree, $newTree);
        $frame = StreamPusher::formatWebSocketFrame($screenId, $patches);
        return StreamPusher::formatSse('sdui_patch', $frame, $eventId);
    }

    /**
     * Validate a component or screen JSON schema against basic specification.
     *
     * @param array<string, mixed> $schema
     * @throws ComponentValidationException
     */
    public function validateSchema(array $schema): bool
    {
        $errors = [];

        if (empty($schema['id']) && empty($schema['screenId'])) {
            $errors['id'] = "Schema must contain either 'id' (for component) or 'screenId' (for screen).";
        }

        if (isset($schema['type']) && !is_string($schema['type'])) {
            $errors['type'] = "Component 'type' attribute must be a string.";
        }

        if (isset($schema['children']) && !is_array($schema['children'])) {
            $errors['children'] = "Component 'children' must be an array.";
        }

        if (!empty($errors)) {
            throw new ComponentValidationException("SDUI Schema validation failed.", $errors);
        }

        return true;
    }
}
