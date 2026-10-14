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

use NetFusion\Core\SDUI\Actions\Action;
use NetFusion\Core\SDUI\Enums\ComponentType;
use NetFusion\Core\SDUI\Schema\BaseComponent;

class ButtonComponent extends BaseComponent
{
    public function getType(): ComponentType
    {
        return ComponentType::BUTTON;
    }

    public static function make(string $label, ?Action $onClick = null, string $variant = 'primary', ?string $id = null): self
    {
        $comp = new self($id, [
            'label'   => $label,
            'variant' => $variant,
        ]);

        if ($onClick !== null) {
            $comp->withAction('onClick', $onClick);
        }

        return $comp;
    }

    public function withIcon(string $iconName, string $position = 'left'): self
    {
        $this->withProp('icon', $iconName);
        $this->withProp('iconPosition', $position);
        return $this;
    }

    public function disabled(bool $disabled = true): self
    {
        return $this->withProp('disabled', $disabled);
    }
}
