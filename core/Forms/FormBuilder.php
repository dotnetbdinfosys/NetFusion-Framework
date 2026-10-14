<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Forms/FormBuilder.php
| Module    : Forms
| Namespace : NetFusion\Core\Forms
| Purpose   : Fluent form builder factory for constructing forms programmatically.
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
use NetFusion\Core\Forms\Fields\CheckboxField;
use NetFusion\Core\Forms\Fields\DateField;
use NetFusion\Core\Forms\Fields\FileField;
use NetFusion\Core\Forms\Fields\JsonField;
use NetFusion\Core\Forms\Fields\NumberField;
use NetFusion\Core\Forms\Fields\SelectField;
use NetFusion\Core\Forms\Fields\TextareaField;
use NetFusion\Core\Forms\Fields\TextField;

/**
 * FormBuilder
 *
 * ফ্লুয়েন্ট মেথডের মাধ্যমে ফর্ম এবং এর ফিল্ডসমূহ সংজ্ঞায়িত করার জন্য বিল্ডার ক্লাস।
 */
class FormBuilder
{
    protected Form $form;

    public function __construct(string $action = '', string $method = 'POST')
    {
        $this->form = new Form($action, $method);
    }

    public static function make(string $action = '', string $method = 'POST'): static
    {
        return new static($action, $method);
    }

    public function text(string $name, ?string $label = null): TextField
    {
        $field = new TextField($name, $label);
        $this->form->add($field);
        return $field;
    }

    public function email(string $name, ?string $label = null): TextField
    {
        $field = (new TextField($name, $label))->email();
        $this->form->add($field);
        return $field;
    }

    public function password(string $name, ?string $label = null): TextField
    {
        $field = (new TextField($name, $label))->password();
        $this->form->add($field);
        return $field;
    }

    public function number(string $name, ?string $label = null): NumberField
    {
        $field = new NumberField($name, $label);
        $this->form->add($field);
        return $field;
    }

    public function select(string $name, ?string $label = null, array $options = []): SelectField
    {
        $field = (new SelectField($name, $label))->options($options);
        $this->form->add($field);
        return $field;
    }

    public function textarea(string $name, ?string $label = null, int $rows = 4): TextareaField
    {
        $field = (new TextareaField($name, $label))->rows($rows);
        $this->form->add($field);
        return $field;
    }

    public function date(string $name, ?string $label = null, bool $withTime = false): DateField
    {
        $field = (new DateField($name, $label))->withTime($withTime);
        $this->form->add($field);
        return $field;
    }

    public function checkbox(string $name, ?string $label = null): CheckboxField
    {
        $field = new CheckboxField($name, $label);
        $this->form->add($field);
        return $field;
    }

    public function file(string $name, ?string $label = null, string $accept = '*/*'): FileField
    {
        $field = (new FileField($name, $label))->accept($accept);
        $this->form->add($field);
        return $field;
    }

    public function json(string $name, ?string $label = null): JsonField
    {
        $field = new JsonField($name, $label);
        $this->form->add($field);
        return $field;
    }

    public function add(FieldInterface $field): static
    {
        $this->form->add($field);
        return $this;
    }

    public function bind(array|object $data): static
    {
        $this->form->bind($data);
        return $this;
    }

    public function build(): Form
    {
        return $this->form;
    }

    public function getForm(): Form
    {
        return $this->form;
    }
}