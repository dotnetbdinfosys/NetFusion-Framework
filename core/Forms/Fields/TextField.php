<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Forms/Fields/TextField.php
| Module    : Forms
| Namespace : NetFusion\Core\Forms\Fields
| Purpose   : Single-line text input form field.
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

class TextField extends Field
{
    protected string $inputType = 'text';

    public function getType(): string
    {
        return 'text';
    }

    public function email(): static
    {
        $this->inputType = 'email';
        $this->rules('email');
        return $this;
    }

    public function password(): static
    {
        $this->inputType = 'password';
        return $this;
    }

    public function url(): static
    {
        $this->inputType = 'url';
        return $this;
    }

    public function render(): string
    {
        $val = htmlspecialchars((string)($this->getValue() ?? ''), ENT_QUOTES, 'UTF-8');
        return sprintf(
            '<input type="%s" name="%s" id="field_%s" value="%s"%s />',
            $this->inputType,
            $this->name,
            $this->name,
            $val,
            $this->renderAttributes()
        );
    }
}