<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Types/InputObjectType.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Types
| Purpose   : GraphQL Input Object Type definition for mutation arguments.
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

class InputObjectType extends GraphQLType
{
    /** @var array<string, Argument>|callable */
    protected mixed $fieldsDefinition = [];
    /** @var array<string, Argument>|null */
    protected ?array $resolvedFields = null;

    /**
     * @param string                           $name
     * @param array<string, Argument>|callable $fields
     * @param string|null                      $description
     */
    public function __construct(
        string $name,
        mixed $fields = [],
        ?string $description = null
    ) {
        parent::__construct($name, $description);
        $this->fieldsDefinition = $fields;
    }

    /**
     * @return array<string, Argument>
     */
    public function getFields(): array
    {
        if ($this->resolvedFields === null) {
            $rawFields = is_callable($this->fieldsDefinition) ? ($this->fieldsDefinition)() : $this->fieldsDefinition;
            $this->resolvedFields = [];

            foreach ($rawFields as $key => $arg) {
                if ($arg instanceof Argument) {
                    $this->resolvedFields[$arg->getName()] = $arg;
                } elseif (is_array($arg)) {
                    $argName = is_string($key) ? $key : ($arg['name'] ?? '');
                    $this->resolvedFields[$argName] = new Argument(
                        name: $argName,
                        type: $arg['type'],
                        description: $arg['description'] ?? null,
                        defaultValue: $arg['defaultValue'] ?? null,
                        hasDefaultValue: array_key_exists('defaultValue', $arg)
                    );
                }
            }
        }

        return $this->resolvedFields;
    }

    public function getField(string $name): ?Argument
    {
        return $this->getFields()[$name] ?? null;
    }
}
