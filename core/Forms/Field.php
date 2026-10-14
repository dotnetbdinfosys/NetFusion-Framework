<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Forms/Field.php
| Module    : Forms
| Namespace : NetFusion\Core\Forms
| Purpose   : Abstract base class for all form field types.
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

namespace NetFusion\Core\Forms;

use NetFusion\Core\Forms\Contracts\FieldInterface;

/**
 * Field
 *
 * ফর্ম ফিল্ডের বেস অ্যাবস্ট্রাক্ট ক্লাস।
 */
abstract class Field implements FieldInterface
{
    protected string $name;
    protected string $label;
    protected mixed $value = null;
    protected mixed $defaultValue = null;
    protected array $rules = [];
    protected array $attributes = [];
    protected string $helpText = '';
    protected bool $required = false;
    protected bool $disabled = false;
    protected bool $readonly = false;

    public function __construct(string $name, ?string $label = null)
    {
        $this->name = $name;
        $this->label = $label ?? ucfirst(str_replace(['_', '-'], ' ', $name));
    }

    public static function make(string $name, ?string $label = null): static
    {
        return new static($name, $label);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    abstract public function getType(): string;

    public function setValue(mixed $value): static
    {
        $this->value = $value;
        return $this;
    }

    public function getValue(): mixed
    {
        return $this->value ?? $this->defaultValue;
    }

    public function default(mixed $value): static
    {
        $this->defaultValue = $value;
        return $this;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function rules(array|string $rules): static
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        $this->rules = array_merge($this->rules, $rules);

        if (in_array('required', $this->rules, true)) {
            $this->required = true;
        }

        return $this;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;
        if ($required && !in_array('required', $this->rules, true)) {
            $this->rules[] = 'required';
        }
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function disabled(bool $disabled = true): static
    {
        $this->disabled = $disabled;
        return $this;
    }

    public function readonly(bool $readonly = true): static
    {
        $this->readonly = $readonly;
        return $this;
    }

    public function help(string $text): static
    {
        $this->helpText = $text;
        return $this;
    }

    public function getHelpText(): string
    {
        return $this->helpText;
    }

    public function attr(string|array $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->attributes = array_merge($this->attributes, $key);
        } else {
            $this->attributes[$key] = $value;
        }
        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function placeholder(string $placeholder): static
    {
        return $this->attr('placeholder', $placeholder);
    }

    public function class(string $class): static
    {
        return $this->attr('class', $class);
    }

    /**
     * ভ্যালিডেশন এক্সিকিউট করে এরর মেসেজ রিটার্ন করে।
     */
    public function validate(mixed $value): array
    {
        $errors = [];
        $val = $value ?? $this->getValue();

        foreach ($this->rules as $rule) {
            $ruleName = $rule;
            $param = null;

            if (str_contains($rule, ':')) {
                [$ruleName, $param] = explode(':', $rule, 2);
            }

            switch ($ruleName) {
                case 'required':
                    if ($val === null || $val === '' || (is_array($val) && empty($val))) {
                        $errors[] = "The {$this->label} field is required.";
                    }
                    break;

                case 'min':
                    if (is_numeric($val) && (float)$val < (float)$param) {
                        $errors[] = "The {$this->label} must be at least {$param}.";
                    } elseif (is_string($val) && mb_strlen($val) < (int)$param) {
                        $errors[] = "The {$this->label} must be at least {$param} characters.";
                    }
                    break;

                case 'max':
                    if (is_numeric($val) && (float)$val > (float)$param) {
                        $errors[] = "The {$this->label} may not be greater than {$param}.";
                    } elseif (is_string($val) && mb_strlen($val) > (int)$param) {
                        $errors[] = "The {$this->label} may not be greater than {$param} characters.";
                    }
                    break;

                case 'email':
                    if ($val !== null && $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "The {$this->label} must be a valid email address.";
                    }
                    break;

                case 'numeric':
                case 'integer':
                    if ($val !== null && $val !== '' && !is_numeric($val)) {
                        $errors[] = "The {$this->label} must be a number.";
                    }
                    break;
            }
        }

        return $errors;
    }

    /**
     * অ্যাট্রিবিউট স্ট্রিং এইচটিএমএল ফরম্যাটে তৈরি করে।
     */
    protected function renderAttributes(): string
    {
        $attrs = $this->attributes;

        if ($this->required) {
            $attrs['required'] = true;
        }
        if ($this->disabled) {
            $attrs['disabled'] = true;
        }
        if ($this->readonly) {
            $attrs['readonly'] = true;
        }

        $html = [];
        foreach ($attrs as $k => $v) {
            if ($v === true) {
                $html[] = htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8');
            } elseif ($v !== false && $v !== null) {
                $html[] = sprintf(
                    '%s="%s"',
                    htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8')
                );
            }
        }

        return !empty($html) ? ' ' . implode(' ', $html) : '';
    }

    /**
     * ফিল্ডের স্কিমা অ্যারে।
     */
    public function toArray(): array
    {
        return [
            'name'          => $this->name,
            'label'         => $this->label,
            'type'          => $this->getType(),
            'value'         => $this->getValue(),
            'default_value' => $this->defaultValue,
            'rules'         => $this->rules,
            'required'      => $this->required,
            'attributes'    => $this->attributes,
            'help_text'     => $this->helpText,
        ];
    }
}