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

use NetFusion\Core\MFE\Enums\RenderMode;

/**
 * Represents a discrete composable UI Fragment stitched into a page shell.
 */
class MfeFragment
{
    protected string $id;
    protected string $remote; // remote container name e.g. 'checkout', 'nav'
    protected string $component; // exposed module name e.g. './Header'
    protected RenderMode $renderMode;
    /** @var array<string, mixed> Props/Attributes passed to component */
    protected array $props;
    protected ?string $fallbackHtml;
    protected int $timeout; // ms

    /**
     * @param array<string, mixed> $props
     */
    public function __construct(
        string $remote,
        string $component,
        array $props = [],
        RenderMode $renderMode = RenderMode::SSR,
        ?string $fallbackHtml = null,
        int $timeout = 3000,
        string $id = ''
    ) {
        $this->remote = $remote;
        $this->component = $component;
        $this->props = $props;
        $this->renderMode = $renderMode;
        $this->fallbackHtml = $fallbackHtml;
        $this->timeout = $timeout;
        $this->id = $id !== '' ? $id : sprintf('mfe-%s-%s-%s', $remote, trim($component, './'), substr(md5(random_bytes(4)), 0, 6));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRemote(): string
    {
        return $this->remote;
    }

    public function getComponent(): string
    {
        return $this->component;
    }

    public function getRenderMode(): RenderMode
    {
        return $this->renderMode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProps(): array
    {
        return $this->props;
    }

    public function getFallbackHtml(): ?string
    {
        return $this->fallbackHtml;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Render the client-side container markup placeholder with hydration attributes.
     */
    public function renderClientPlaceholder(): string
    {
        $propsJson = htmlspecialchars(json_encode($this->props), ENT_QUOTES, 'UTF-8');
        return sprintf(
            '<div id="%s" data-mfe-remote="%s" data-mfe-component="%s" data-mfe-props="%s" data-mfe-mode="%s">%s</div>',
            htmlspecialchars($this->id),
            htmlspecialchars($this->remote),
            htmlspecialchars($this->component),
            $propsJson,
            $this->renderMode->value,
            $this->fallbackHtml ?? ''
        );
    }
}
