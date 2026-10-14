<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Forms/Fields/TextareaField.php
| Module    : Forms
| Namespace : NetFusion\Core\Forms\Fields
| Purpose   : Multi-line text area input form field.
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

class TextareaField extends Field
{
    protected int $rows = 4;

    public function getType(): string
    {
        return 'textarea';
    }

    public function rows(int $rows): static
    {
        $this->rows = $rows;
        $this->attr('rows', $rows);
        return $this;
    }

    public function render(): string
    {
        $val = htmlspecialchars((string)($this->getValue() ?? ''), ENT_QUOTES, 'UTF-8');
        $this->attr('rows', $this->rows);

        return sprintf(
            '<textarea name="%s" id="field_%s"%s>%s</textarea>',
            $this->name,
            $this->name,
            $this->renderAttributes(),
            $val
        );
    }
}