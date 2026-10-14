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

class GridComponent extends BaseComponent
{
    public function getType(): ComponentType
    {
        return ComponentType::GRID;
    }

    public static function make(int $columns = 2, array $children = [], ?string $id = null): self
    {
        $comp = new self($id, ['columns' => $columns]);
        $comp->addChildren($children);
        return $comp;
    }

    public function withColumns(int $columns): self
    {
        return $this->withProp('columns', $columns);
    }

    public function withGaps(int|string $rowGap, int|string $columnGap): self
    {
        $this->withStyle('rowGap', $rowGap);
        $this->withStyle('columnGap', $columnGap);
        return $this;
    }
}
