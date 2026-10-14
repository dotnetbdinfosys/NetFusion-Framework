<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Forms/Fields/FileField.php
| Module    : Forms
| Namespace : NetFusion\Core\Forms\Fields
| Purpose   : File upload input form field.
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

class FileField extends Field
{
    protected string $accept = '*/*';

    public function getType(): string
    {
        return 'file';
    }

    public function accept(string $accept): static
    {
        $this->accept = $accept;
        $this->attr('accept', $accept);
        return $this;
    }

    public function render(): string
    {
        return sprintf(
            '<input type="file" name="%s" id="field_%s"%s />',
            $this->name,
            $this->name,
            $this->renderAttributes()
        );
    }
}