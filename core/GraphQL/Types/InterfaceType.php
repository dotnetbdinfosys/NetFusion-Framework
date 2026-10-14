<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Types/InterfaceType.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Types
| Purpose   : GraphQL Interface Type definition with runtime type resolution.
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

class InterfaceType extends GraphQLType
{
    /** @var array<string, Field>|callable */
    protected mixed $fieldsDefinition = [];
    /** @var array<string, Field>|null */
    protected ?array $resolvedFields = null;
    /** @var callable|null */
    protected $typeResolver = null;

    /**
     * @param string                        $name
     * @param array<string, Field>|callable $fields
     * @param string|null                   $description
     * @param callable|null                 $typeResolver fn($value, $context, $info): ObjectType|string
     */
    public function __construct(
        string $name,
        mixed $fields = [],
        ?string $description = null,
        ?callable $typeResolver = null
    ) {
        parent::__construct($name, $description);
        $this->fieldsDefinition = $fields;
        $this->typeResolver = $typeResolver;
    }

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
                        arguments: $field['args'] ?? []
                    );
                }
            }
        }

        return $this->resolvedFields;
    }

    public function resolveType(mixed $value, mixed $context, mixed $info): mixed
    {
        if ($this->typeResolver !== null) {
            return ($this->typeResolver)($value, $context, $info);
        }
        return null;
    }
}
