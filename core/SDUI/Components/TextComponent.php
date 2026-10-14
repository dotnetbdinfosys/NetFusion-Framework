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

class TextComponent extends BaseComponent
{
    public function getType(): ComponentType
    {
        return ComponentType::TEXT;
    }

    public static function make(string $content, string $variant = 'body', ?string $id = null): self
    {
        return new self($id, [
            'content' => $content,
            'variant' => $variant,
        ]);
    }

    public function withColor(string $color): self
    {
        return $this->withStyle('color', $color);
    }

    public function withWeight(string|int $weight): self
    {
        return $this->withStyle('fontWeight', $weight);
    }

    public function withAlign(string $alignment): self
    {
        return $this->withStyle('textAlign', $alignment);
    }
}
