<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Forms/Traits/HasCustomFields.php
| Module    : Forms
| Namespace : NetFusion\Core\Forms\Traits
| Purpose   : Model trait allowing models to store and query dynamic custom field values.
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

namespace NetFusion\Core\Forms\Traits;

use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Forms\Form;
use NetFusion\Core\Forms\FormSchema;

/**
 * HasCustomFields
 *
 * যেকোনো ActiveRecord মডেলে ডাইনামিক ফিল্ড ভ্যালু সংরক্ষণ ও রিট্রিভ করার ট্রেইট।
 */
trait HasCustomFields
{
    /**
     * ইন-মেমোরি ক্যাশড কাস্টম ফিল্ড ভ্যালু
     * @var array<string, mixed>|null
     */
    protected ?array $customFieldValuesCache = null;

    /**
     * মডেলের EntityType নাম পান (default: class basename)।
     */
    public function getCustomFieldEntityType(): string
    {
        if (property_exists($this, 'customFieldEntityType') && !empty($this->customFieldEntityType)) {
            return $this->customFieldEntityType;
        }

        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * মডেলের কাস্টম ফিল্ড ভ্যালু পান।
     */
    public function getCustomField(string $name, mixed $default = null): mixed
    {
        $all = $this->getCustomFields();
        return $all[$name] ?? $default;
    }

    /**
     * একটি কাস্টম ফিল্ডের মান সেট বা আপডেট করুন।
     */
    public function setCustomField(string $name, mixed $value): static
    {
        $entityType = $this->getCustomFieldEntityType();
        $entityId = (int)$this->getAttribute($this->primaryKey ?? 'id');

        /** @var DatabaseManager $db */
        $db = app(DatabaseManager::class);

        // Find field definition
        $def = $db->connection()->selectOne(
            "SELECT id FROM custom_field_definitions WHERE entity_type = ? AND name = ?",
            [$entityType, $name]
        );

        if ($def === null) {
            throw new \RuntimeException("Custom field [{$name}] is not defined for entity [{$entityType}].");
        }

        $fieldId = (int)$def['id'];
        $valStr = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
        $now = date('Y-m-d H:i:s');

        $db->connection()->statement(
            "INSERT INTO custom_field_values (entity_type, entity_id, field_id, value, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON CONFLICT(entity_type, entity_id, field_id) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at",
            [$entityType, $entityId, $fieldId, $valStr, $now, $now]
        );

        if ($this->customFieldValuesCache !== null) {
            $this->customFieldValuesCache[$name] = $value;
        }

        return $this;
    }

    /**
     * মডেলের সকল কাস্টম ফিল্ডের ভ্যালু অ্যারে আকারে পান।
     *
     * @return array<string, mixed>
     */
    public function getCustomFields(): array
    {
        if ($this->customFieldValuesCache !== null) {
            return $this->customFieldValuesCache;
        }

        $entityType = $this->getCustomFieldEntityType();
        $entityId = (int)$this->getAttribute($this->primaryKey ?? 'id');

        if (!$entityId) {
            return [];
        }

        /** @var DatabaseManager $db */
        $db = app(DatabaseManager::class);

        $rows = $db->connection()->select(
            "SELECT d.name, d.type, v.value 
             FROM custom_field_definitions d
             LEFT JOIN custom_field_values v 
               ON v.field_id = d.id AND v.entity_id = ? AND v.entity_type = ?
             WHERE d.entity_type = ?
             ORDER BY d.order_index ASC, d.id ASC",
            [$entityId, $entityType, $entityType]
        );

        $result = [];
        foreach ($rows as $r) {
            $raw = $r['value'];
            if ($r['type'] === 'json' && is_string($raw)) {
                $raw = json_decode($raw, true) ?? $raw;
            } elseif ($r['type'] === 'checkbox') {
                $raw = (bool)$raw;
            } elseif ($r['type'] === 'number' && is_numeric($raw)) {
                $raw = str_contains((string)$raw, '.') ? (float)$raw : (int)$raw;
            }
            $result[$r['name']] = $raw;
        }

        $this->customFieldValuesCache = $result;
        return $result;
    }

    /**
     * একসাথে একাধিক কাস্টম ফিল্ড সেভ করুন।
     *
     * @param array<string, mixed> $values
     */
    public function saveCustomFields(array $values): static
    {
        foreach ($values as $name => $val) {
            $this->setCustomField((string)$name, $val);
        }
        return $this;
    }

    /**
     * মডেলের জন্য স্বয়ংক্রিয় Form অবজেক্ট পান যা বর্তমান ভ্যালুর সাথে প্রি-বাইন্ডেড।
     */
    public function getCustomForm(string $action = '', string $method = 'POST'): Form
    {
        /** @var DatabaseManager $db */
        $db = app(DatabaseManager::class);
        $schema = new FormSchema($db);

        $form = $schema->forEntity($this->getCustomFieldEntityType(), $action, $method);
        $form->bind($this->getCustomFields());

        return $form;
    }
}