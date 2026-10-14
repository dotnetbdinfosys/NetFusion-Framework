<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Forms/Fields/NumberField.php
| Module    : Forms
| Namespace : NetFusion\Core\Forms\Fields
| Purpose   : Numeric input form field with min, max, step controls.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\Forms\Fields;

use NetFusion\Core\Forms\Field;

class NumberField extends Field
{
    public function getType(): string
    {
        return 'number';
    }

    public function min(int|float $min): static
    {
        $this->attr('min', (string)$min);
        $this->rules("min:{$min}");
        return $this;
    }

    public function max(int|float $max): static
    {
        $this->attr('max', (string)$max);
        $this->rules("max:{$max}");
        return $this;
    }

    public function step(int|float|string $step): static
    {
        $this->attr('step', (string)$step);
        return $this;
    }

    public function render(): string
    {
        $val = htmlspecialchars((string)($this->getValue() ?? ''), ENT_QUOTES, 'UTF-8');
        return sprintf(
            '<input type="number" name="%s" id="field_%s" value="%s"%s />',
            $this->name,
            $this->name,
            $val,
            $this->renderAttributes()
        );
    }
}