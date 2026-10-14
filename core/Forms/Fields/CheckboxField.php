<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Forms/Fields/CheckboxField.php
| Module    : Forms
| Namespace : NetFusion\Core\Forms\Fields
| Purpose   : Boolean toggle / single checkbox form field.
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

class CheckboxField extends Field
{
    public function getType(): string
    {
        return 'checkbox';
    }

    public function isChecked(): bool
    {
        $val = $this->getValue();
        return $val === true || $val === '1' || $val === 1 || $val === 'on';
    }

    public function render(): string
    {
        $checked = $this->isChecked() ? ' checked' : '';

        return sprintf(
            '<input type="checkbox" name="%s" id="field_%s" value="1"%s%s />',
            $this->name,
            $this->name,
            $checked,
            $this->renderAttributes()
        );
    }
}