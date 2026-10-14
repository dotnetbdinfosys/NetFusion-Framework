<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Types/EnumType.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Types
| Purpose   : GraphQL Enum Type definition.
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

class EnumType extends GraphQLType
{
    /** @var array<string, array{value: mixed, description: ?string, deprecationReason: ?string}> */
    protected array $values = [];

    /**
     * @param string                     $name
     * @param array<string|int, mixed>   $values
     * @param string|null                $description
     */
    public function __construct(
        string $name,
        array $values,
        ?string $description = null
    ) {
        parent::__construct($name, $description);

        foreach ($values as $key => $config) {
            if (is_int($key) && is_string($config)) {
                $this->values[$config] = [
                    'value'             => $config,
                    'description'       => null,
                    'deprecationReason' => null,
                ];
            } elseif (is_string($key)) {
                if (is_array($config)) {
                    $this->values[$key] = [
                        'value'             => $config['value'] ?? $key,
                        'description'       => $config['description'] ?? null,
                        'deprecationReason' => $config['deprecationReason'] ?? null,
                    ];
                } else {
                    $this->values[$key] = [
                        'value'             => $config,
                        'description'       => null,
                        'deprecationReason' => null,
                    ];
                }
            }
        }
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function isValidName(string $name): bool
    {
        return isset($this->values[$name]);
    }

    public function getValue(string $name): mixed
    {
        return $this->values[$name]['value'] ?? null;
    }

    public function getNameForValue(mixed $value): ?string
    {
        foreach ($this->values as $name => $meta) {
            if ($meta['value'] === $value) {
                return $name;
            }
        }
        return null;
    }
}
