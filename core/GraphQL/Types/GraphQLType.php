<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Types/GraphQLType.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Types
| Purpose   : Base abstract class for GraphQL types with built-in type helpers.
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

use NetFusion\Core\GraphQL\Contracts\TypeInterface;

abstract class GraphQLType implements TypeInterface
{
    public function __construct(
        protected string $name,
        protected ?string $description = null
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function toString(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    // -------------------------------------------------------------
    // Static Type Helpers
    // -------------------------------------------------------------

    public static function string(): ScalarType
    {
        return ScalarType::string();
    }

    public static function int(): ScalarType
    {
        return ScalarType::int();
    }

    public static function float(): ScalarType
    {
        return ScalarType::float();
    }

    public static function boolean(): ScalarType
    {
        return ScalarType::boolean();
    }

    public static function id(): ScalarType
    {
        return ScalarType::id();
    }

    public static function dateTime(): ScalarType
    {
        return ScalarType::dateTime();
    }

    public static function json(): ScalarType
    {
        return ScalarType::json();
    }

    public static function nonNull(TypeInterface $type): NonNullType
    {
        return new NonNullType($type);
    }

    public static function listOf(TypeInterface $type): ListType
    {
        return new ListType($type);
    }
}
