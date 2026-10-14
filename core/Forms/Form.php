<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Forms/Form.php
| Module    : Forms
| Namespace : NetFusion\Core\Forms
| Purpose   : Form instance encapsulating fields, data binding, validation and rendering.
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
 * Form
 *
 * ফর্মের সার্বিক স্টেট, ফিল্ড কালেকশন, ডাটা বাইন্ডিং, ভ্যালিডেশন এবং এইচটিএমএল আউটপুট পরিচালনা করে।
 */
class Form
{
    /**
     * @var array<string, FieldInterface>
     */
    protected array $fields = [];

    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @var array<string, array<int, string>>
     */
    protected array $errors = [];

    protected string $action = '';
    protected string $method = 'POST';
    protected array $attributes = [];
    protected bool $hasFile = false;

    public function __construct(string $action = '', string $method = 'POST')
    {
        $this->action = $action;
        $this->method = strtoupper($method);
    }

    public static function create(string $action = '', string $method = 'POST'): static
    {
        return new static($action, $method);
    }

    public function add(FieldInterface $field): static
    {
        $this->fields[$field->getName()] = $field;
        if ($field->getType() === 'file') {
            $this->hasFile = true;
        }
        return $this;
    }

    public function getField(string $name): ?FieldInterface
    {
        return $this->fields[$name] ?? null;
    }

    public function hasField(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    /**
     * @return array<string, FieldInterface>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function removeField(string $name): static
    {
        unset($this->fields[$name]);
        return $this;
    }

    /**
     * ডাটা বাইন্ড করুন (Request ডাটা বা মডেল ডাটা)।
     *
     * @param array<string, mixed>|object $data
     */
    public function bind(array|object $data): static
    {
        if (is_object($data)) {
            $data = method_exists($data, 'toArray') ? $data->toArray() : get_object_vars($data);
        }

        $this->data = $data;

        foreach ($this->fields as $name => $field) {
            if (array_key_exists($name, $data)) {
                $field->setValue($data[$name]);
            }
        }

        return $this;
    }

    /**
     * ফর্ম ডাটা ভ্যালিডেট করুন।
     */
    public function validate(?array $data = null): bool
    {
        if ($data !== null) {
            $this->bind($data);
        }

        $this->errors = [];

        foreach ($this->fields as $name => $field) {
            $val = $this->data[$name] ?? $field->getValue();
            $fieldErrors = $field->validate($val);
            if (!empty($fieldErrors)) {
                $this->errors[$name] = $fieldErrors;
            }
        }

        return empty($this->errors);
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<int, string>
     */
    public function getError(string $fieldName): array
    {
        return $this->errors[$fieldName] ?? [];
    }

    public function hasError(string $fieldName): bool
    {
        return !empty($this->errors[$fieldName]);
    }

    /**
     * ভ্যালিডেটেড এবং বাইন্ডেড সমস্ত ভ্যালু পান।
     *
     * @return array<string, mixed>
     */
    public function getValues(): array
    {
        $values = [];
        foreach ($this->fields as $name => $field) {
            $values[$name] = $field->getValue();
        }
        return $values;
    }

    public function getValue(string $name): mixed
    {
        return $this->fields[$name]?->getValue();
    }

    public function action(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function method(string $method): static
    {
        $this->method = strtoupper($method);
        return $this;
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

    public function renderOpen(): string
    {
        $attrs = $this->attributes;
        $attrs['action'] = $this->action;
        $realMethod = in_array($this->method, ['GET', 'POST'], true) ? $this->method : 'POST';
        $attrs['method'] = $realMethod;

        if ($this->hasFile) {
            $attrs['enctype'] = 'multipart/form-data';
        }

        $attrStrings = [];
        foreach ($attrs as $k => $v) {
            $attrStrings[] = sprintf('%s="%s"', htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8'), htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'));
        }

        $html = sprintf('<form %s>', implode(' ', $attrStrings));

        // Method spoofing for PUT/PATCH/DELETE
        if (!in_array($this->method, ['GET', 'POST'], true)) {
            $html .= sprintf('<input type="hidden" name="_method" value="%s" />', $this->method);
        }

        // CSRF token injection if helper exists
        if (function_exists('csrf_field') && $this->method !== 'GET') {
            $html .= csrf_field();
        }

        return $html;
    }

    public function renderClose(): string
    {
        return '</form>';
    }

    /**
     * একটি নির্দিষ্ট ফিল্ডকে লেবেল ও এরর মেসেজ সহ ফর্ম গ্রুপে রেন্ডার করুন।
     */
    public function renderField(string $name): string
    {
        $field = $this->getField($name);
        if ($field === null) {
            return '';
        }

        $hasErr = $this->hasError($name);
        $errHtml = '';
        if ($hasErr) {
            $errors = $this->getError($name);
            $errHtml = sprintf('<div class="form-error">%s</div>', htmlspecialchars($errors[0], ENT_QUOTES, 'UTF-8'));
        }

        $helpHtml = '';
        $help = method_exists($field, 'getHelpText') ? $field->getHelpText() : '';
        if (!empty($help)) {
            $helpHtml = sprintf('<small class="form-help">%s</small>', htmlspecialchars($help, ENT_QUOTES, 'UTF-8'));
        }

        $reqMark = $field->isRequired() ? ' <span class="text-danger">*</span>' : '';

        return sprintf(
            '<div class="form-group%s"><label for="field_%s">%s%s</label>%s%s%s</div>',
            $hasErr ? ' has-error' : '',
            $field->getName(),
            htmlspecialchars($field->getLabel(), ENT_QUOTES, 'UTF-8'),
            $reqMark,
            $field->render(),
            $helpHtml,
            $errHtml
        );
    }

    /**
     * সম্পূর্ণ ফর্ম HTML স্ট্রিং হিসেবে রেন্ডার করুন।
     */
    public function render(string $submitLabel = 'Submit'): string
    {
        $html = [$this->renderOpen()];

        foreach ($this->fields as $field) {
            $html[] = $this->renderField($field->getName());
        }

        $html[] = sprintf(
            '<div class="form-actions"><button type="submit" class="btn btn-primary">%s</button></div>',
            htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8')
        );

        $html[] = $this->renderClose();

        return implode("\n", $html);
    }

    /**
     * সম্পূর্ণ ফর্ম স্কিমা অ্যারে (JSON serialization এর জন্য)।
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $fieldsArr = [];
        foreach ($this->fields as $name => $field) {
            $fieldsArr[$name] = $field->toArray();
        }

        return [
            'action'     => $this->action,
            'method'     => $this->method,
            'has_file'   => $this->hasFile,
            'attributes' => $this->attributes,
            'fields'     => $fieldsArr,
            'errors'     => $this->errors,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }
}