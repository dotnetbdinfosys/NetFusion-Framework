<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Types/SchemaBuilder.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Types
| Purpose   : Fluent API & SDL Schema Builder for GraphQL types and resolvers.
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

use InvalidArgumentException;
use NetFusion\Core\GraphQL\Contracts\TypeInterface;
use NetFusion\Core\GraphQL\Parser\Lexer;
use NetFusion\Core\GraphQL\Parser\Token;

class SchemaBuilder
{
    /** @var array<string, Field> */
    protected array $queryFields = [];
    /** @var array<string, Field> */
    protected array $mutationFields = [];
    /** @var array<string, Field> */
    protected array $subscriptionFields = [];
    /** @var array<string, TypeInterface> */
    protected array $types = [];

    /**
     * Add a query field.
     */
    public function query(string $name, TypeInterface $type, mixed $resolver = null, array $args = [], ?string $description = null): self
    {
        $this->queryFields[$name] = new Field($name, $type, $description, $args, $resolver);
        return $this;
    }

    /**
     * Add a mutation field.
     */
    public function mutation(string $name, TypeInterface $type, mixed $resolver = null, array $args = [], ?string $description = null): self
    {
        $this->mutationFields[$name] = new Field($name, $type, $description, $args, $resolver);
        return $this;
    }

    /**
     * Define an Object type.
     */
    public function type(string $name, mixed $fields, ?string $description = null): ObjectType
    {
        $type = new ObjectType($name, $fields, $description);
        $this->types[$name] = $type;
        return $type;
    }

    /**
     * Define an Input Object type.
     */
    public function input(string $name, mixed $fields, ?string $description = null): InputObjectType
    {
        $type = new InputObjectType($name, $fields, $description);
        $this->types[$name] = $type;
        return $type;
    }

    /**
     * Define an Enum type.
     */
    public function enum(string $name, array $values, ?string $description = null): EnumType
    {
        $type = new EnumType($name, $values, $description);
        $this->types[$name] = $type;
        return $type;
    }

    /**
     * Build the executable Schema instance.
     */
    public function build(): Schema
    {
        $queryType = new ObjectType('Query', $this->queryFields);
        $mutationType = !empty($this->mutationFields) ? new ObjectType('Mutation', $this->mutationFields) : null;
        $subscriptionType = !empty($this->subscriptionFields) ? new ObjectType('Subscription', $this->subscriptionFields) : null;

        return new Schema(
            queryType: $queryType,
            mutationType: $mutationType,
            subscriptionType: $subscriptionType,
            additionalTypes: array_values($this->types)
        );
    }

    /**
     * Build Schema from Schema Definition Language (SDL) string and resolvers map.
     *
     * @param string               $sdl
     * @param array<string, mixed> $resolvers Map of ['Query' => ['field' => fn()], 'Mutation' => [...]]
     */
    public static function fromSDL(string $sdl, array $resolvers = []): Schema
    {
        $builder = new self();
        $lexer = new Lexer($sdl);
        $lexer->advance();

        $rawTypes = []; // name => ['kind' => 'type|input|enum', 'fields' => [...]]

        while ($lexer->getToken()->kind !== Token::EOF) {
            $token = $lexer->getToken();
            if ($token->kind !== Token::NAME) {
                $lexer->advance();
                continue;
            }

            $keyword = $token->value;
            $lexer->advance();

            if ($keyword === 'type' || $keyword === 'input') {
                $typeName = $lexer->getToken()->value;
                $lexer->advance(); // skip name
                $rawTypes[$typeName] = [
                    'kind'   => $keyword,
                    'fields' => self::parseSdlFields($lexer),
                ];
            } elseif ($keyword === 'enum') {
                $enumName = $lexer->getToken()->value;
                $lexer->advance();
                $values = self::parseSdlEnumValues($lexer);
                $builder->enum($enumName, $values);
            }
        }

        // Second pass: instantiate types with resolvers
        $typeRegistry = [];

        foreach ($rawTypes as $typeName => $meta) {
            if ($meta['kind'] === 'type') {
                $typeRegistry[$typeName] = new ObjectType($typeName, function () use ($meta, &$typeRegistry, $typeName, $resolvers) {
                    $fields = [];
                    foreach ($meta['fields'] as $fieldName => $fieldMeta) {
                        $type = self::resolveTypeReference($fieldMeta['typeStr'], $typeRegistry);
                        $resolver = $resolvers[$typeName][$fieldName] ?? null;

                        $args = [];
                        foreach ($fieldMeta['args'] as $argName => $argMeta) {
                            $args[$argName] = new Argument(
                                name: $argName,
                                type: self::resolveTypeReference($argMeta['typeStr'], $typeRegistry),
                                defaultValue: $argMeta['defaultValue'] ?? null
                            );
                        }

                        $fields[$fieldName] = new Field(
                            name: $fieldName,
                            type: $type,
                            arguments: $args,
                            resolver: $resolver
                        );
                    }
                    return $fields;
                });
            } elseif ($meta['kind'] === 'input') {
                $typeRegistry[$typeName] = new InputObjectType($typeName, function () use ($meta, &$typeRegistry) {
                    $fields = [];
                    foreach ($meta['fields'] as $fieldName => $fieldMeta) {
                        $type = self::resolveTypeReference($fieldMeta['typeStr'], $typeRegistry);
                        $fields[$fieldName] = new Argument(
                            name: $fieldName,
                            type: $type
                        );
                    }
                    return $fields;
                });
            }
        }

        $queryType = $typeRegistry['Query'] ?? new ObjectType('Query', []);
        $mutationType = $typeRegistry['Mutation'] ?? null;
        $subscriptionType = $typeRegistry['Subscription'] ?? null;

        unset($typeRegistry['Query'], $typeRegistry['Mutation'], $typeRegistry['Subscription']);

        return new Schema(
            queryType: $queryType,
            mutationType: $mutationType,
            subscriptionType: $subscriptionType,
            additionalTypes: array_values($typeRegistry)
        );
    }

    protected static function parseSdlFields(Lexer $lexer): array
    {
        $fields = [];
        if ($lexer->getToken()->kind === Token::BRACE_L) {
            $lexer->advance();

            while ($lexer->getToken()->kind !== Token::BRACE_R && $lexer->getToken()->kind !== Token::EOF) {
                if ($lexer->getToken()->kind === Token::NAME) {
                    $fieldName = $lexer->getToken()->value;
                    $lexer->advance();

                    $args = [];
                    if ($lexer->getToken()->kind === Token::PAREN_L) {
                        $lexer->advance();
                        while ($lexer->getToken()->kind !== Token::PAREN_R && $lexer->getToken()->kind !== Token::EOF) {
                            if ($lexer->getToken()->kind === Token::NAME) {
                                $argName = $lexer->getToken()->value;
                                $lexer->advance();
                                if ($lexer->getToken()->kind === Token::COLON) {
                                    $lexer->advance();
                                }
                                $argTypeStr = self::readTypeString($lexer);
                                $defaultValue = null;
                                if ($lexer->getToken()->kind === Token::EQUALS) {
                                    $lexer->advance();
                                    $tok = $lexer->getToken();
                                    $defaultValue = match ($tok->kind) {
                                        Token::INT          => (int) $tok->value,
                                        Token::FLOAT        => (float) $tok->value,
                                        Token::STRING,
                                        Token::BLOCK_STRING => $tok->value,
                                        Token::NAME         => match ($tok->value) {
                                            'true'  => true,
                                            'false' => false,
                                            'null'  => null,
                                            default => $tok->value,
                                        },
                                        default             => $tok->value,
                                    };
                                    $lexer->advance();
                                }
                                $args[$argName] = ['typeStr' => $argTypeStr, 'defaultValue' => $defaultValue];
                            } else {
                                $lexer->advance();
                            }
                        }
                        if ($lexer->getToken()->kind === Token::PAREN_R) {
                            $lexer->advance();
                        }
                    }

                    if ($lexer->getToken()->kind === Token::COLON) {
                        $lexer->advance();
                    }

                    $typeStr = self::readTypeString($lexer);
                    $fields[$fieldName] = ['typeStr' => $typeStr, 'args' => $args];
                } else {
                    $lexer->advance();
                }
            }

            if ($lexer->getToken()->kind === Token::BRACE_R) {
                $lexer->advance();
            }
        }

        return $fields;
    }

    protected static function parseSdlEnumValues(Lexer $lexer): array
    {
        $values = [];
        if ($lexer->getToken()->kind === Token::BRACE_L) {
            $lexer->advance();
            while ($lexer->getToken()->kind !== Token::BRACE_R && $lexer->getToken()->kind !== Token::EOF) {
                if ($lexer->getToken()->kind === Token::NAME) {
                    $values[] = $lexer->getToken()->value;
                }
                $lexer->advance();
            }
            if ($lexer->getToken()->kind === Token::BRACE_R) {
                $lexer->advance();
            }
        }
        return $values;
    }

    protected static function readTypeString(Lexer $lexer): string
    {
        $str = '';
        if ($lexer->getToken()->kind === Token::BRACKET_L) {
            $str .= '[';
            $lexer->advance();
            $str .= self::readTypeString($lexer);
            if ($lexer->getToken()->kind === Token::BRACKET_R) {
                $str .= ']';
                $lexer->advance();
            }
        } elseif ($lexer->getToken()->kind === Token::NAME) {
            $str .= $lexer->getToken()->value;
            $lexer->advance();
        }

        if ($lexer->getToken()->kind === Token::BANG) {
            $str .= '!';
            $lexer->advance();
        }

        return $str;
    }

    protected static function resolveTypeReference(string $typeStr, array &$registry): TypeInterface
    {
        $isNonNull = str_ends_with($typeStr, '!');
        $innerStr = $isNonNull ? substr($typeStr, 0, -1) : $typeStr;

        $isList = str_starts_with($innerStr, '[') && str_ends_with($innerStr, ']');
        if ($isList) {
            $itemStr = substr($innerStr, 1, -1);
            $itemType = self::resolveTypeReference($itemStr, $registry);
            $type = new ListType($itemType);
        } else {
            $baseName = $innerStr;
            $type = match ($baseName) {
                'String'   => ScalarType::string(),
                'Int'      => ScalarType::int(),
                'Float'    => ScalarType::float(),
                'Boolean'  => ScalarType::boolean(),
                'ID'       => ScalarType::id(),
                'DateTime' => ScalarType::dateTime(),
                'JSON'     => ScalarType::json(),
                default    => $registry[$baseName] ?? throw new InvalidArgumentException("Unknown GraphQL type [{$baseName}] in schema."),
            };
        }

        return $isNonNull ? new NonNullType($type) : $type;
    }
}
