<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Types/ScalarType.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Types
| Purpose   : Built-in and custom GraphQL scalar type definitions.
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

use DateTimeInterface;
use InvalidArgumentException;
use NetFusion\Core\GraphQL\Parser\BooleanValueNode;
use NetFusion\Core\GraphQL\Parser\FloatValueNode;
use NetFusion\Core\GraphQL\Parser\IntValueNode;
use NetFusion\Core\GraphQL\Parser\Node;
use NetFusion\Core\GraphQL\Parser\StringValueNode;

class ScalarType extends GraphQLType
{
    /** @var callable|null */
    protected $serializer = null;
    /** @var callable|null */
    protected $valueParser = null;
    /** @var callable|null */
    protected $literalParser = null;

    public function __construct(
        string $name,
        ?string $description = null,
        ?callable $serializer = null,
        ?callable $valueParser = null,
        ?callable $literalParser = null
    ) {
        parent::__construct($name, $description);
        $this->serializer = $serializer;
        $this->valueParser = $valueParser;
        $this->literalParser = $literalParser;
    }

    /**
     * Serializes an internal value to include in a response.
     */
    public function serialize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($this->serializer !== null) {
            return ($this->serializer)($value);
        }

        return match ($this->name) {
            'Int'      => (int) $value,
            'Float'    => (float) $value,
            'String'   => (string) $value,
            'Boolean'  => (bool) $value,
            'ID'       => (string) $value,
            'DateTime' => $value instanceof DateTimeInterface ? $value->format(DateTimeInterface::ATOM) : (string) $value,
            'JSON'     => is_string($value) ? json_decode($value, true) : $value,
            default    => $value,
        };
    }

    /**
     * Parses an input value, e.g. from query variables.
     */
    public function parseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($this->valueParser !== null) {
            return ($this->valueParser)($value);
        }

        return match ($this->name) {
            'Int'      => is_numeric($value) ? (int) $value : throw new InvalidArgumentException("Int cannot represent non-numeric value: {$value}"),
            'Float'    => is_numeric($value) ? (float) $value : throw new InvalidArgumentException("Float cannot represent non-numeric value: {$value}"),
            'String'   => (string) $value,
            'Boolean'  => (bool) $value,
            'ID'       => (string) $value,
            'DateTime' => (string) $value,
            'JSON'     => is_string($value) ? json_decode($value, true) : $value,
            default    => $value,
        };
    }

    /**
     * Parses an AST literal node into internal PHP value.
     */
    public function parseLiteral(Node $valueNode): mixed
    {
        if ($this->literalParser !== null) {
            return ($this->literalParser)($valueNode);
        }

        return match ($this->name) {
            'Int'      => $valueNode instanceof IntValueNode ? $valueNode->value : null,
            'Float'    => $valueNode instanceof FloatValueNode || $valueNode instanceof IntValueNode ? (float) $valueNode->value : null,
            'String', 'ID', 'DateTime' => $valueNode instanceof StringValueNode ? $valueNode->value : null,
            'Boolean'  => $valueNode instanceof BooleanValueNode ? $valueNode->value : null,
            default    => null,
        };
    }

    // -------------------------------------------------------------
    // Singletons for standard GraphQL scalars
    // -------------------------------------------------------------

    protected static ?ScalarType $stringScalar = null;
    protected static ?ScalarType $intScalar = null;
    protected static ?ScalarType $floatScalar = null;
    protected static ?ScalarType $booleanScalar = null;
    protected static ?ScalarType $idScalar = null;
    protected static ?ScalarType $dateTimeScalar = null;
    protected static ?ScalarType $jsonScalar = null;

    public static function string(): self
    {
        return self::$stringScalar ??= new self('String', 'The String scalar type represents textual data.');
    }

    public static function int(): self
    {
        return self::$intScalar ??= new self('Int', 'The Int scalar type represents signed 32-bit numeric non-fractional values.');
    }

    public static function float(): self
    {
        return self::$floatScalar ??= new self('Float', 'The Float scalar type represents signed double-precision fractional values.');
    }

    public static function boolean(): self
    {
        return self::$booleanScalar ??= new self('Boolean', 'The Boolean scalar type represents true or false.');
    }

    public static function id(): self
    {
        return self::$idScalar ??= new self('ID', 'The ID scalar type represents a unique identifier.');
    }

    public static function dateTime(): self
    {
        return self::$dateTimeScalar ??= new self('DateTime', 'The DateTime scalar type represents ISO-8601 formatted date-time string.');
    }

    public static function json(): self
    {
        return self::$jsonScalar ??= new self('JSON', 'The JSON scalar type represents arbitrary JSON data.');
    }
}
