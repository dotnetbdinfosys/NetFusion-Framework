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

class ModalComponent extends BaseComponent
{
    public function getType(): ComponentType
    {
        return ComponentType::MODAL;
    }

    public static function make(string $modalId, string $title, array $children = [], ?string $id = null): self
    {
        $comp = new self($id, [
            'modalId'     => $modalId,
            'title'       => $title,
            'dismissible' => true,
        ]);
        $comp->addChildren($children);
        return $comp;
    }

    public function dismissible(bool $dismissible = true): self
    {
        return $this->withProp('dismissible', $dismissible);
    }
}
