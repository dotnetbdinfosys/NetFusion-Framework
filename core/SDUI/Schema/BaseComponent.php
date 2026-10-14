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

namespace NetFusion\Core\SDUI\Schema;

use JsonSerializable;
use NetFusion\Core\SDUI\Actions\Action;
use NetFusion\Core\SDUI\Enums\ComponentType;
use NetFusion\Core\SDUI\Enums\PlatformType;

abstract class BaseComponent implements ComponentInterface
{
    protected string $id;

    /** @var array<string, mixed> */
    protected array $props = [];

    /** @var array<int, ComponentInterface> */
    protected array $children = [];

    /** @var array<string, Action> */
    protected array $actions = [];

    /** @var array<string, mixed> */
    protected array $styles = [];

    /** @var array<int, PlatformType> */
    protected array $platforms = [PlatformType::ALL];

    /** @var callable|array<string, mixed>|null */
    protected mixed $visibilityRule = null;

    public function __construct(?string $id = null, array $props = [])
    {
        $this->id = $id ?? uniqid($this->getType()->value . '_', true);
        $this->props = $props;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function withId(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getProps(): array
    {
        return $this->props;
    }

    public function getProp(string $key, mixed $default = null): mixed
    {
        return $this->props[$key] ?? $default;
    }

    public function withProp(string $key, mixed $value): static
    {
        $this->props[$key] = $value;
        return $this;
    }

    public function withProps(array $props): static
    {
        $this->props = array_merge($this->props, $props);
        return $this;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChild(ComponentInterface $child): static
    {
        $this->children[] = $child;
        return $this;
    }

    /**
     * @param array<int, ComponentInterface> $children
     */
    public function addChildren(array $children): static
    {
        foreach ($children as $child) {
            $this->addChild($child);
        }
        return $this;
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function withAction(string $event, Action $action): static
    {
        $this->actions[$event] = $action;
        return $this;
    }

    public function getStyles(): array
    {
        return $this->styles;
    }

    public function withStyle(string $key, mixed $value): static
    {
        $this->styles[$key] = $value;
        return $this;
    }

    public function withStyles(array $styles): static
    {
        $this->styles = array_merge($this->styles, $styles);
        return $this;
    }

    public function getPlatforms(): array
    {
        return $this->platforms;
    }

    public function forPlatforms(PlatformType ...$platforms): static
    {
        $this->platforms = $platforms;
        return $this;
    }

    /**
     * Set conditional visibility callback or condition rule.
     *
     * @param callable|array<string, mixed> $condition
     */
    public function visibleWhen(callable|array $condition): static
    {
        $this->visibilityRule = $condition;
        return $this;
    }

    public function isVisible(array $context = []): bool
    {
        // Platform check
        if (isset($context['platform'])) {
            $currentPlatform = $context['platform'];
            if ($currentPlatform instanceof PlatformType) {
                $currentPlatform = $currentPlatform->value;
            }

            $allowed = false;
            foreach ($this->platforms as $p) {
                if ($p === PlatformType::ALL || $p->value === $currentPlatform) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                return false;
            }
        }

        // Custom condition check
        if ($this->visibilityRule === null) {
            return true;
        }

        if (is_callable($this->visibilityRule)) {
            return (bool) ($this->visibilityRule)($context);
        }

        if (is_array($this->visibilityRule)) {
            foreach ($this->visibilityRule as $key => $expectedValue) {
                if (!isset($context[$key]) || $context[$key] !== $expectedValue) {
                    return false;
                }
            }
            return true;
        }

        return true;
    }

    public function toArray(): array
    {
        $schema = [
            'id'   => $this->getId(),
            'type' => $this->getType()->value,
        ];

        if (!empty($this->props)) {
            $schema['props'] = $this->props;
        }

        if (!empty($this->styles)) {
            $schema['styles'] = $this->styles;
        }

        if (!empty($this->actions)) {
            $actionsArray = [];
            foreach ($this->actions as $event => $action) {
                $actionsArray[$event] = $action->toArray();
            }
            $schema['actions'] = $actionsArray;
        }

        if (!empty($this->children)) {
            $childrenArray = [];
            foreach ($this->children as $child) {
                $childrenArray[] = $child->toArray();
            }
            $schema['children'] = $childrenArray;
        }

        if (!empty($this->platforms) && !in_array(PlatformType::ALL, $this->platforms, true)) {
            $schema['platforms'] = array_map(fn(PlatformType $p) => $p->value, $this->platforms);
        }

        return $schema;
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
