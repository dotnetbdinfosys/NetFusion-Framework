<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Types/UnionType.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Types
| Purpose   : GraphQL Union Type definition.
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

class UnionType extends GraphQLType
{
    /** @var array<ObjectType> */
    protected array $types = [];
    /** @var callable|null */
    protected $typeResolver = null;

    /**
     * @param string             $name
     * @param array<ObjectType>  $types
     * @param string|null        $description
     * @param callable|null      $typeResolver
     */
    public function __construct(
        string $name,
        array $types,
        ?string $description = null,
        ?callable $typeResolver = null
    ) {
        parent::__construct($name, $description);
        $this->types = $types;
        $this->typeResolver = $typeResolver;
    }

    /**
     * @return array<ObjectType>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function resolveType(mixed $value, mixed $context, mixed $info): mixed
    {
        if ($this->typeResolver !== null) {
            return ($this->typeResolver)($value, $context, $info);
        }
        return null;
    }
}
