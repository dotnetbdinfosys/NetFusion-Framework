<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Forms/Fields/DateField.php
| Module    : Forms
| Namespace : NetFusion\Core\Forms\Fields
| Purpose   : Date / DateTime picker form field.
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

class DateField extends Field
{
    protected string $format = 'Y-m-d';
    protected bool $includeTime = false;

    public function getType(): string
    {
        return 'date';
    }

    public function withTime(bool $withTime = true): static
    {
        $this->includeTime = $withTime;
        $this->format = $withTime ? 'Y-m-d\TH:i' : 'Y-m-d';
        return $this;
    }

    public function render(): string
    {
        $val = htmlspecialchars((string)($this->getValue() ?? ''), ENT_QUOTES, 'UTF-8');
        $type = $this->includeTime ? 'datetime-local' : 'date';

        return sprintf(
            '<input type="%s" name="%s" id="field_%s" value="%s"%s />',
            $type,
            $this->name,
            $this->name,
            $val,
            $this->renderAttributes()
        );
    }
}