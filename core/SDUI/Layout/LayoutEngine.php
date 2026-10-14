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

use NetFusion\Core\SDUI\Exceptions\SDUIException;
use NetFusion\Core\SDUI\Rules\PersonalizationRule;
use NetFusion\Core\SDUI\Schema\ComponentInterface;

/**
 * Orchestrates dynamic screen layout composition and personalization injection.
 */
class LayoutEngine
{
    /** @var array<string, Screen|callable> */
    protected array $screens = [];

    /** @var array<int, array{rule: PersonalizationRule, callback: callable}> */
    protected array $personalizationInjectors = [];

    /**
     * Register a screen template.
     */
    public function registerScreen(string $screenId, Screen|callable $screen): self
    {
        $this->screens[$screenId] = $screen;
        return $this;
    }

    public function hasScreen(string $screenId): bool
    {
        return isset($this->screens[$screenId]);
    }

    /**
     * Resolve a screen instance.
     *
     * @param array<string, mixed> $context
     */
    public function resolveScreen(string $screenId, array $context = []): Screen
    {
        if (!$this->hasScreen($screenId)) {
            throw new SDUIException("Screen with ID '{$screenId}' is not registered in LayoutEngine.");
        }

        $screenDef = $this->screens[$screenId];
        $screen = is_callable($screenDef) ? $screenDef($context) : clone $screenDef;

        if (!$screen instanceof Screen) {
            throw new SDUIException("Screen resolver for '{$screenId}' must return an instance of Screen.");
        }

        return $screen;
    }

    /**
     * Register a dynamic component injector with a personalization rule.
     */
    public function addPersonalization(PersonalizationRule $rule, callable $injector): self
    {
        $this->personalizationInjectors[] = [
            'rule'     => $rule,
            'callback' => $injector,
        ];
        return $this;
    }

    /**
     * Render screen with context and apply personalization injectors.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function render(string $screenId, array $context = []): array
    {
        $screen = $this->resolveScreen($screenId, $context);

        // Apply personalization injectors
        foreach ($this->personalizationInjectors as $injector) {
            if ($injector['rule']->matches($context)) {
                ($injector['callback'])($screen, $context);
            }
        }

        return $screen->render($context);
    }
}
