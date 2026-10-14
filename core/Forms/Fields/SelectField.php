<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Forms/Fields/SelectField.php
| Module    : Forms
| Namespace : NetFusion\Core\Forms\Fields
| Purpose   : Dropdown select input form field with key-value options.
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

class SelectField extends Field
{
    protected array $options = [];
    protected ?string $emptyOption = null;
    protected bool $multiple = false;

    public function getType(): string
    {
        return 'select';
    }

    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function emptyOption(?string $label = 'Select an option'): static
    {
        $this->emptyOption = $label;
        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;
        if ($multiple) {
            $this->attr('multiple', true);
        }
        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function render(): string
    {
        $current = $this->getValue();
        $name = $this->multiple ? "{$this->name}[]" : $this->name;

        $optionsHtml = [];
        if ($this->emptyOption !== null) {
            $optionsHtml[] = sprintf('<option value="">%s</option>', htmlspecialchars($this->emptyOption, ENT_QUOTES, 'UTF-8'));
        }

        foreach ($this->options as $val => $text) {
            $valStr = (string)$val;
            $selected = false;

            if ($this->multiple && is_array($current)) {
                $selected = in_array($valStr, array_map('strval', $current), true);
            } elseif ($current !== null && (string)$current === $valStr) {
                $selected = true;
            }

            $optionsHtml[] = sprintf(
                '<option value="%s"%s>%s</option>',
                htmlspecialchars($valStr, ENT_QUOTES, 'UTF-8'),
                $selected ? ' selected' : '',
                htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8')
            );
        }

        return sprintf(
            '<select name="%s" id="field_%s"%s>%s</select>',
            $name,
            $this->name,
            $this->renderAttributes(),
            implode('', $optionsHtml)
        );
    }

    public function toArray(): array
    {
        $arr = parent::toArray();
        $arr['options'] = $this->options;
        $arr['multiple'] = $this->multiple;
        return $arr;
    }
}