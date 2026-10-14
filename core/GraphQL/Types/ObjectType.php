<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Types/ObjectType.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Types
| Purpose   : GraphQL Object Type definition with fields & interface resolution.
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

namespace NetFusion\Core\GraphQL\Types;

use Closure;

class ObjectType extends GraphQLType
{
    /** @var array<string, Field>|callable */
    protected mixed $fieldsDefinition = [];
    /** @var array<string, Field>|null */
    protected ?array $resolvedFields = null;
    /** @var array<InterfaceType> */
    protected array $interfaces = [];

    /**
     * @param string                        $name
     * @param array<string, Field>|callable $fields
     * @param string|null                   $description
     * @param array<InterfaceType>          $interfaces
     */
    public function __construct(
        string $name,
        mixed $fields = [],
        ?string $description = null,
        array $interfaces = []
    ) {
        parent::__construct($name, $description);
        $this->fieldsDefinition = $fields;
        $this->interfaces = $interfaces;
    }

    /**
     * Get all fields for this Object type.
     *
     * @return array<string, Field>
     */
    public function getFields(): array
    {
        if ($this->resolvedFields === null) {
            $rawFields = is_callable($this->fieldsDefinition) ? ($this->fieldsDefinition)() : $this->fieldsDefinition;
            $this->resolvedFields = [];

            foreach ($rawFields as $key => $field) {
                if ($field instanceof Field) {
                    $this->resolvedFields[$field->getName()] = $field;
                } elseif (is_array($field)) {
                    $fieldName = is_string($key) ? $key : ($field['name'] ?? '');
                    $this->resolvedFields[$fieldName] = new Field(
                        name: $fieldName,
                        type: $field['type'],
                        description: $field['description'] ?? null,
                        arguments: $field['args'] ?? $field['arguments'] ?? [],
                        resolver: $field['resolve'] ?? $field['resolver'] ?? null,
                        deprecationReason: $field['deprecationReason'] ?? null
                    );
                }
            }
        }

        return $this->resolvedFields;
    }

    public function getField(string $name): ?Field
    {
        $fields = $this->getFields();
        return $fields[$name] ?? null;
    }

    public function hasField(string $name): bool
    {
        return isset($this->getFields()[$name]);
    }

    /**
     * @return array<InterfaceType>
     */
    public function getInterfaces(): array
    {
        return $this->interfaces;
    }
}
