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

class InputComponent extends BaseComponent
{
    public function getType(): ComponentType
    {
        return ComponentType::INPUT;
    }

    public static function make(string $name, string $label, string $inputType = 'text', ?string $id = null): self
    {
        return new self($id, [
            'name'      => $name,
            'label'     => $label,
            'inputType' => $inputType,
        ]);
    }

    public function withPlaceholder(string $placeholder): self
    {
        return $this->withProp('placeholder', $placeholder);
    }

    public function withDefaultValue(mixed $value): self
    {
        return $this->withProp('defaultValue', $value);
    }

    public function required(bool $required = true): self
    {
        return $this->withProp('required', $required);
    }

    /**
     * @param array<int, string> $rules
     */
    public function withValidationRules(array $rules): self
    {
        return $this->withProp('rules', $rules);
    }
}
