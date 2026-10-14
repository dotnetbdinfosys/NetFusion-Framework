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

interface ComponentInterface extends JsonSerializable
{
    /**
     * Get component primitive type.
     */
    public function getType(): ComponentType;

    /**
     * Get unique component identifier.
     */
    public function getId(): string;

    /**
     * Get component property attributes.
     *
     * @return array<string, mixed>
     */
    public function getProps(): array;

    /**
     * Get child components.
     *
     * @return array<int, ComponentInterface>
     */
    public function getChildren(): array;

    /**
     * Get registered event action handlers.
     *
     * @return array<string, Action>
     */
    public function getActions(): array;

    /**
     * Get design tokens and visual styles.
     *
     * @return array<string, mixed>
     */
    public function getStyles(): array;

    /**
     * Get allowed target platforms.
     *
     * @return array<int, PlatformType>
     */
    public function getPlatforms(): array;

    /**
     * Evaluate conditional visibility based on client context.
     *
     * @param array<string, mixed> $context
     */
    public function isVisible(array $context = []): bool;

    /**
     * Convert component and descendant tree to schema array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Render component tree as serialized JSON string.
     */
    public function toJson(int $flags = 0): string;
}
