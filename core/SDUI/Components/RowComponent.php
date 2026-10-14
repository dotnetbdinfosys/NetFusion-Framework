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
use NetFusion\Core\SDUI\Schema\ComponentInterface;

class RowComponent extends BaseComponent
{
    public function getType(): ComponentType
    {
        return ComponentType::ROW;
    }

    /**
     * @param array<int, ComponentInterface> $children
     */
    public static function make(array $children = [], ?string $id = null): self
    {
        $comp = new self($id);
        $comp->addChildren($children);
        return $comp;
    }

    public function withSpacing(int|string $gap): self
    {
        return $this->withStyle('gap', $gap);
    }

    public function withAlignment(string $justifyContent, string $alignItems = 'center'): self
    {
        $this->withStyle('justifyContent', $justifyContent);
        $this->withStyle('alignItems', $alignItems);
        return $this;
    }
}
