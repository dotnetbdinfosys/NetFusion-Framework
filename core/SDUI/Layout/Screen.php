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

namespace NetFusion\Core\SDUI\Layout;

use JsonSerializable;
use NetFusion\Core\SDUI\Schema\ComponentInterface;

/**
 * Represents a complete Server-Driven UI screen/view layout.
 */
class Screen implements JsonSerializable
{
    protected ?ComponentInterface $appbar = null;
    protected ?ComponentInterface $body = null;
    protected ?ComponentInterface $floatingActionButton = null;
    protected ?ComponentInterface $bottomNav = null;

    /** @var array<string, mixed> */
    protected array $state = [];

    /** @var array<string, mixed> */
    protected array $metadata = [];

    protected ThemeContext $theme;

    public function __construct(
        protected string $screenId,
        protected string $title = '',
        ?ThemeContext $theme = null
    ) {
        $this->theme = $theme ?? new ThemeContext();
    }

    public static function make(string $screenId, string $title = '', ?ThemeContext $theme = null): self
    {
        return new self($screenId, $title, $theme);
    }

    public function getScreenId(): string
    {
        return $this->screenId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function withTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function withAppbar(?ComponentInterface $appbar): self
    {
        $this->appbar = $appbar;
        return $this;
    }

    public function withBody(?ComponentInterface $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function withFloatingActionButton(?ComponentInterface $fab): self
    {
        $this->floatingActionButton = $fab;
        return $this;
    }

    public function withBottomNav(?ComponentInterface $bottomNav): self
    {
        $this->bottomNav = $bottomNav;
        return $this;
    }

    public function withState(array $state): self
    {
        $this->state = array_merge($this->state, $state);
        return $this;
    }

    public function withMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    public function withTheme(ThemeContext $theme): self
    {
        $this->theme = $theme;
        return $this;
    }

    public function getTheme(): ThemeContext
    {
        return $this->theme;
    }

    /**
     * Compile and render screen tree into hydration schema array.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function render(array $context = []): array
    {
        $rendered = [
            'schemaVersion' => '1.0.0',
            'screenId'      => $this->screenId,
            'title'         => $this->title,
            'theme'         => $this->theme->toArray(),
            'state'         => $this->state,
            'metadata'      => $this->metadata,
        ];

        if ($this->appbar !== null && $this->appbar->isVisible($context)) {
            $rendered['appbar'] = $this->renderComponentTree($this->appbar, $context);
        }

        if ($this->body !== null && $this->body->isVisible($context)) {
            $rendered['body'] = $this->renderComponentTree($this->body, $context);
        }

        if ($this->floatingActionButton !== null && $this->floatingActionButton->isVisible($context)) {
            $rendered['floatingActionButton'] = $this->renderComponentTree($this->floatingActionButton, $context);
        }

        if ($this->bottomNav !== null && $this->bottomNav->isVisible($context)) {
            $rendered['bottomNav'] = $this->renderComponentTree($this->bottomNav, $context);
        }

        return $rendered;
    }

    /**
     * Recursively prune invisible child components and compile schema.
     *
     * @param ComponentInterface $component
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function renderComponentTree(ComponentInterface $component, array $context): array
    {
        $data = $component->toArray();

        if (!empty($component->getChildren())) {
            $visibleChildren = [];
            foreach ($component->getChildren() as $child) {
                if ($child->isVisible($context)) {
                    $visibleChildren[] = $this->renderComponentTree($child, $context);
                }
            }
            $data['children'] = $visibleChildren;
        }

        return $data;
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->render(), $flags | JSON_THROW_ON_ERROR);
    }

    public function jsonSerialize(): array
    {
        return $this->render();
    }
}
