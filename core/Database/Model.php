<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Database/Model.php
| Module    : Core\Database
| Namespace : NetFusion\Core\Database
| Purpose   : Active Record ORM base model with fillable mass assignment protection and timestamps.
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

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Database/Model.php
| Module    : Core\Database
| Namespace : NetFusion\Core\Database
| Purpose   : Active Record ORM base model with fillable mass assignment protection and timestamps.
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

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Database/Model.php
| Module    : Core\Database
| Namespace : NetFusion\Core\Database
| Purpose   : Active Record ORM base model with fillable mass assignment protection and timestamps.
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

namespace NetFusion\Core\Database;

use NetFusion\Core\Database\Query\Builder;
use RuntimeException;

/**
 * NetFusion Active Record Base Model
 *
 * Provides Active Record capabilities, mass-assignment protection ($fillable),
 * hidden attribute exclusion ($hidden), timestamps auto-updating, and CRUD shortcuts.
 *
 * @package NetFusion\Core\Database
 * @version 1.0.0
 */
abstract class Model
{
    /**
     * Database table name (inferred from class name if empty).
     */
    protected string $table = '';

    /**
     * Primary key column name.
     */
    protected string $primaryKey = 'id';

    /**
     * Whether primary key is auto-incrementing integer.
     */
    protected bool $incrementing = true;

    /**
     * Specific database connection name for this model.
     */
    protected ?string $connection = null;

    /**
     * Attributes that are mass assignable.
     *
     * @var string[]
     */
    protected array $fillable = [];

    /**
     * Attributes that should be hidden from array / JSON conversions.
     *
     * @var string[]
     */
    protected array $hidden = [];

    /**
     * Indicates if the model should be timestamped with created_at / updated_at.
     */
    protected bool $timestamps = true;

    /**
     * The model's attributes.
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * The original attributes as loaded from database.
     *
     * @var array<string, mixed>
     */
    protected array $original = [];

    /**
     * Indicates if the record already exists in the database.
     */
    public bool $exists = false;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        if ($this->table !== '') {
            return $this->table;
        }

        // Default: snake_case class basename with trailing 's'
        $basename = class_basename(static::class);
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $basename)) . 's';
    }

    /**
     * Get primary key column name.
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get primary key value.
     */
    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Fill model attributes with mass assignment protection.
     *
     * @param  array<string, mixed> $attributes
     * @return static
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Determine if an attribute can be mass assigned.
     */
    public function isFillable(string $key): bool
    {
        if (empty($this->fillable)) {
            return true; // If fillable is empty, allow all by default
        }

        return in_array($key, $this->fillable, true);
    }

    /**
     * Save the model to the database (INSERT or UPDATE).
     */
    public function save(): bool
    {
        $query = static::query();

        if ($this->timestamps) {
            $now = now()->format('Y-m-d H:i:s');
            $this->setAttribute('updated_at', $now);

            if (!$this->exists && !isset($this->attributes['created_at'])) {
                $this->setAttribute('created_at', $now);
            }
        }

        if ($this->exists) {
            // Update existing record
            $affected = $query->where($this->getKeyName(), $this->getKey())
                              ->update($this->attributes);

            $this->original = $this->attributes;
            return $affected >= 0;
        }

        // Insert new record
        $id = $query->insert($this->attributes);

        if ($this->incrementing && ($id !== 0 && $id !== '0')) {
            $this->setAttribute($this->getKeyName(), $id);
        }

        $this->exists   = true;
        $this->original = $this->attributes;

        return true;
    }

    /**
     * Delete the model from the database.
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $affected = static::query()->where($this->getKeyName(), $this->getKey())->delete();
        $this->exists = false;

        return $affected > 0;
    }

    /**
     * Get a new query builder instance for this model's table.
     */
    public static function query(): Builder
    {
        /** @var DatabaseManager $db */
        $db = app(DatabaseManager::class);
        $instance = new static();

        return $db->table($instance->getTable(), $instance->connection);
    }

    /**
     * Find a model by primary key.
     *
     * @param  int|string $id
     * @return static|null
     */
    public static function find(int|string $id): ?static
    {
        $instance = new static();
        $data = static::query()->where($instance->getKeyName(), $id)->first();

        return $data ? static::newFromBuilder($data) : null;
    }

    /**
     * Get all records for this model.
     *
     * @return static[]
     */
    public static function all(): array
    {
        $rows = static::query()->get();
        return array_map([static::class, 'newFromBuilder'], $rows);
    }

    /**
     * Create and save a new model instance.
     *
     * @param  array<string, mixed> $attributes
     * @return static
     */
    public static function create(array $attributes = []): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /**
     * Instantiate a model from a raw database row.
     *
     * @param  array<string, mixed> $row
     * @return static
     */
    public static function newFromBuilder(array $row): static
    {
        $model = new static();
        $model->attributes = $row;
        $model->original   = $row;
        $model->exists     = true;

        return $model;
    }

    // =========================================================================
    //  Attribute Accessors & Mutators
    // =========================================================================

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Get all of the current attributes on the model.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get the model's original attribute values.
     *
     * @param  string|null $key
     * @param  mixed       $default
     * @return mixed
     */
    public function getOriginal(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->original;
        }

        return $this->original[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function toArray(): array
    {
        $attributes = $this->attributes;

        foreach ($this->hidden as $hiddenField) {
            unset($attributes[$hiddenField]);
        }

        return $attributes;
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Dynamically pass missing static calls to the query builder.
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return static::query()->$method(...$parameters);
    }
}
