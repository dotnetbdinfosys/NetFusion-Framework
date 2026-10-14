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

namespace NetFusion\Core\SDUI\Components;

use NetFusion\Core\SDUI\Enums\ComponentType;
use NetFusion\Core\SDUI\Schema\BaseComponent;

class ContainerComponent extends BaseComponent
{
    public function getType(): ComponentType
    {
        return ComponentType::CONTAINER;
    }

    public static function make(?string $id = null, array $props = []): self
    {
        return new self($id, $props);
    }

    public function withPadding(int|string $padding): self
    {
        return $this->withStyle('padding', $padding);
    }

    public function withMargin(int|string $margin): self
    {
        return $this->withStyle('margin', $margin);
    }

    public function withBackground(string $color): self
    {
        return $this->withStyle('backgroundColor', $color);
    }

    public function withBorderRadius(int|string $radius): self
    {
        return $this->withStyle('borderRadius', $radius);
    }
}
