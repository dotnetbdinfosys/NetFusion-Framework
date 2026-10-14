<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Forms/Fields/JsonField.php
| Module    : Forms
| Namespace : NetFusion\Core\Forms\Fields
| Purpose   : JSON editor or structured key-value form field.
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

class JsonField extends Field
{
    public function getType(): string
    {
        return 'json';
    }

    public function render(): string
    {
        $val = $this->getValue();
        if (is_array($val)) {
            $val = json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $escaped = htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
        return sprintf(
            '<textarea name="%s" id="field_%s" data-type="json"%s>%s</textarea>',
            $this->name,
            $this->name,
            $this->renderAttributes(),
            $escaped
        );
    }

    public function validate(mixed $value): array
    {
        $errors = parent::validate($value);
        $val = $value ?? $this->getValue();

        if ($val !== null && $val !== '' && is_string($val)) {
            json_decode($val);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = "The {$this->label} must be a valid JSON string.";
            }
        }

        return $errors;
    }
}