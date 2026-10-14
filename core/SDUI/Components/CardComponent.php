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

class CardComponent extends BaseComponent
{
    public function getType(): ComponentType
    {
        return ComponentType::CARD;
    }

    public static function make(?string $title = null, array $children = [], ?string $id = null): self
    {
        $props = [];
        if ($title !== null) {
            $props['title'] = $title;
        }
        $comp = new self($id, $props);
        $comp->addChildren($children);
        return $comp;
    }

    public function withElevation(int $level): self
    {
        return $this->withProp('elevation', $level);
    }

    public function withSubtitle(string $subtitle): self
    {
        return $this->withProp('subtitle', $subtitle);
    }
}
