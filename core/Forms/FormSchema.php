<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Forms/FormSchema.php
| Module    : Forms
| Namespace : NetFusion\Core\Forms
| Purpose   : Schema parser and dynamic field definition manager.
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

use InvalidArgumentException;
use NetFusion\Core\Database\DatabaseManager;
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
 * FormSchema
 *
 * অ্যারে, JSON অথবা ডাটাবেস স্কিমা থেকে ডাইনামিক ফর্ম তৈরি ও ফিল্ড ম্যানেজমেন্ট করে।
 */
class FormSchema
{
    public function __construct(
        protected ?DatabaseManager $db = null
    ) {}

    /**
     * ডিক্লেয়ারেটিভ অ্যারে স্কিমা থেকে ফর্ম বিল্ড করুন।
     *
     * @param array<string, mixed> $schema
     */
    public static function fromArray(array $schema, string $action = '', string $method = 'POST'): Form
    {
        $form = new Form($action, $method);

        $fields = $schema['fields'] ?? $schema;

        foreach ($fields as $key => $fieldDef) {
            $name = $fieldDef['name'] ?? (is_string($key) ? $key : "field_{$key}");
            $field = self::createFieldFromDefinition($name, $fieldDef);
            $form->add($field);
        }

        return $form;
    }

    /**
     * JSON স্কিমা স্ট্রিং থেকে ফর্ম বিল্ড করুন।
     */
    public static function fromJson(string $json, string $action = '', string $method = 'POST'): Form
    {
        $schema = json_decode($json, true);
        if (!is_array($schema)) {
            throw new InvalidArgumentException("Invalid JSON form schema provided.");
        }

        return self::fromArray($schema, $action, $method);
    }

    /**
     * ফিল্ড ডেফিনিশন অ্যারে থেকে উপযুক্ত FieldInterface তৈরি করুন।
     *
     * @param array<string, mixed> $def
     */
    public static function createFieldFromDefinition(string $name, array $def): FieldInterface
    {
        $label = $def['label'] ?? null;
        $type = strtolower((string)($def['type'] ?? 'text'));

        $field = match ($type) {
            'text'     => new TextField($name, $label),
            'email'    => (new TextField($name, $label))->email(),
            'password' => (new TextField($name, $label))->password(),
            'url'      => (new TextField($name, $label))->url(),
            'number'   => new NumberField($name, $label),
            'select'   => (new SelectField($name, $label))->options($def['options'] ?? []),
            'textarea' => (new TextareaField($name, $label))->rows((int)($def['rows'] ?? 4)),
            'date'     => (new DateField($name, $label))->withTime(!empty($def['with_time'])),
            'checkbox' => new CheckboxField($name, $label),
            'file'     => (new FileField($name, $label))->accept($def['accept'] ?? '*/*'),
            'json'     => new JsonField($name, $label),
            default    => new TextField($name, $label),
        };

        if (!empty($def['rules'])) {
            $field->rules($def['rules']);
        }

        if (!empty($def['required']) || (!empty($def['is_required']))) {
            $field->required(true);
        }

        if (array_key_exists('default_value', $def) && $def['default_value'] !== null) {
            $field->default($def['default_value']);
        } elseif (array_key_exists('default', $def) && $def['default'] !== null) {
            $field->default($def['default']);
        }

        if (!empty($def['help_text'])) {
            $field->help((string)$def['help_text']);
        }

        if (!empty($def['attributes']) && is_array($def['attributes'])) {
            $field->attr($def['attributes']);
        }

        return $field;
    }

    /**
     * ডাটাবেসের `custom_field_definitions` টেবিল থেকে কোনো নির্দিষ্ট EntityType-এর ফর্ম স্কিমা বিল্ড করুন।
     */
    public function forEntity(string $entityType, string $action = '', string $method = 'POST'): Form
    {
        if ($this->db === null) {
            throw new \RuntimeException("DatabaseManager required to load entity form schema.");
        }

        $rows = $this->db->connection()->select(
            "SELECT * FROM custom_field_definitions WHERE entity_type = ? ORDER BY order_index ASC, id ASC",
            [$entityType]
        );

        $form = new Form($action, $method);

        foreach ($rows as $row) {
            $options = !empty($row['options']) ? json_decode((string)$row['options'], true) : [];
            $def = [
                'name'          => $row['name'],
                'label'         => $row['label'],
                'type'          => $row['type'],
                'rules'         => $row['rules'],
                'options'       => is_array($options) ? $options : [],
                'default_value' => $row['default_value'],
                'is_required'   => (int)$row['is_required'] === 1,
            ];

            $field = self::createFieldFromDefinition($row['name'], $def);
            $form->add($field);
        }

        return $form;
    }

    /**
     * ডাটাবেসে একটি কাস্টম ফিল্ড তৈরি বা আপডেট করুন।
     *
     * @param array<string, mixed> $options
     */
    public function defineField(
        string $entityType,
        string $name,
        string $label,
        string $type = 'text',
        string|array $rules = '',
        array $options = [],
        mixed $defaultValue = null,
        bool $isRequired = false,
        int $orderIndex = 0
    ): int {
        if ($this->db === null) {
            throw new \RuntimeException("DatabaseManager required to define custom field.");
        }

        $rulesStr = is_array($rules) ? implode('|', $rules) : $rules;
        $optionsJson = json_encode($options, JSON_UNESCAPED_UNICODE);
        $reqInt = $isRequired ? 1 : 0;
        $now = date('Y-m-d H:i:s');

        // Check if exists
        $existing = $this->db->connection()->selectOne(
            "SELECT id FROM custom_field_definitions WHERE entity_type = ? AND name = ?",
            [$entityType, $name]
        );

        if ($existing !== null) {
            $this->db->connection()->statement(
                "UPDATE custom_field_definitions 
                 SET label = ?, type = ?, rules = ?, options = ?, default_value = ?, is_required = ?, order_index = ?, updated_at = ?
                 WHERE id = ?",
                [$label, $type, $rulesStr, $optionsJson, (string)$defaultValue, $reqInt, $orderIndex, $now, $existing['id']]
            );
            return (int)$existing['id'];
        }

        $this->db->connection()->statement(
            "INSERT INTO custom_field_definitions 
             (entity_type, name, label, type, rules, options, default_value, is_required, order_index, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$entityType, $name, $label, $type, $rulesStr, $optionsJson, (string)$defaultValue, $reqInt, $orderIndex, $now, $now]
        );

        return (int)$this->db->connection()->getPdo()->lastInsertId();
    }

    /**
     * একটি নির্দিষ্ট EntityType-এর সমস্ত ফিল্ড ডেফিনিশন পান।
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFieldsForEntity(string $entityType): array
    {
        if ($this->db === null) {
            return [];
        }

        return $this->db->connection()->select(
            "SELECT * FROM custom_field_definitions WHERE entity_type = ? ORDER BY order_index ASC, id ASC",
            [$entityType]
        );
    }

    /**
     * একটি কাস্টম ফিল্ড মুছে ফেলুন।
     */
    public function deleteField(string $entityType, string $name): bool
    {
        if ($this->db === null) {
            return false;
        }

        $field = $this->db->connection()->selectOne(
            "SELECT id FROM custom_field_definitions WHERE entity_type = ? AND name = ?",
            [$entityType, $name]
        );

        if ($field === null) {
            return false;
        }

        $this->db->connection()->statement(
            "DELETE FROM custom_field_values WHERE entity_type = ? AND field_id = ?",
            [$entityType, $field['id']]
        );

        $this->db->connection()->statement(
            "DELETE FROM custom_field_definitions WHERE id = ?",
            [$field['id']]
        );

        return true;
    }
}