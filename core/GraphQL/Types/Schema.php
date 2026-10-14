<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Types/Schema.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Types
| Purpose   : GraphQL Executable Schema container and type map.
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

class Schema
{
    /** @var array<string, TypeInterface> */
    protected array $typeMap = [];
    /** @var array<string, Directive> */
    protected array $directives = [];

    /**
     * @param ObjectType               $queryType
     * @param ObjectType|null          $mutationType
     * @param ObjectType|null          $subscriptionType
     * @param array<TypeInterface>     $additionalTypes
     * @param array<Directive>         $directives
     */
    public function __construct(
        protected ObjectType $queryType,
        protected ?ObjectType $mutationType = null,
        protected ?ObjectType $subscriptionType = null,
        array $additionalTypes = [],
        array $directives = []
    ) {
        $this->addStandardScalars();
        $this->addStandardDirectives();

        $this->registerType($this->queryType);
        if ($this->mutationType !== null) {
            $this->registerType($this->mutationType);
        }
        if ($this->subscriptionType !== null) {
            $this->registerType($this->subscriptionType);
        }

        foreach ($additionalTypes as $type) {
            $this->registerType($type);
        }

        foreach ($directives as $directive) {
            $this->directives[$directive->getName()] = $directive;
        }
    }

    protected function addStandardScalars(): void
    {
        $this->typeMap['String']   = ScalarType::string();
        $this->typeMap['Int']      = ScalarType::int();
        $this->typeMap['Float']    = ScalarType::float();
        $this->typeMap['Boolean']  = ScalarType::boolean();
        $this->typeMap['ID']       = ScalarType::id();
        $this->typeMap['DateTime'] = ScalarType::dateTime();
        $this->typeMap['JSON']     = ScalarType::json();
    }

    protected function addStandardDirectives(): void
    {
        $this->directives['include']    = Directive::includeDirective();
        $this->directives['skip']       = Directive::skipDirective();
        $this->directives['deprecated'] = Directive::deprecatedDirective();
    }

    public function registerType(TypeInterface $type): void
    {
        $named = $this->getNamedType($type);
        $name = $named->getName();

        if (isset($this->typeMap[$name])) {
            return;
        }

        $this->typeMap[$name] = $named;

        // Traverse sub-types
        if ($named instanceof ObjectType || $named instanceof InterfaceType) {
            foreach ($named->getFields() as $field) {
                $this->registerType($field->getType());
                foreach ($field->getArguments() as $arg) {
                    $this->registerType($arg->getType());
                }
            }
        } elseif ($named instanceof InputObjectType) {
            foreach ($named->getFields() as $arg) {
                $this->registerType($arg->getType());
            }
        } elseif ($named instanceof UnionType) {
            foreach ($named->getTypes() as $subType) {
                $this->registerType($subType);
            }
        }
    }

    /**
     * Unwrap NonNull and List wrappers to get underlying Named Type.
     */
    public function getNamedType(TypeInterface $type): TypeInterface
    {
        if ($type instanceof NonNullType) {
            return $this->getNamedType($type->getWrappedType());
        }
        if ($type instanceof ListType) {
            return $this->getNamedType($type->getItemType());
        }
        return $type;
    }

    public function getQueryType(): ObjectType
    {
        return $this->queryType;
    }

    public function getMutationType(): ?ObjectType
    {
        return $this->mutationType;
    }

    public function getSubscriptionType(): ?ObjectType
    {
        return $this->subscriptionType;
    }

    public function getType(string $name): ?TypeInterface
    {
        return $this->typeMap[$name] ?? null;
    }

    /**
     * @return array<string, TypeInterface>
     */
    public function getTypeMap(): array
    {
        return $this->typeMap;
    }

    /**
     * @return array<string, Directive>
     */
    public function getDirectives(): array
    {
        return $this->directives;
    }

    public function getDirective(string $name): ?Directive
    {
        return $this->directives[$name] ?? null;
    }

    /**
     * Print standard Schema Definition Language (SDL) string.
     */
    public function printSDL(): string
    {
        $sdl = "schema {\n";
        $sdl .= "  query: {$this->queryType->getName()}\n";
        if ($this->mutationType) {
            $sdl .= "  mutation: {$this->mutationType->getName()}\n";
        }
        if ($this->subscriptionType) {
            $sdl .= "  subscription: {$this->subscriptionType->getName()}\n";
        }
        $sdl .= "}\n\n";

        foreach ($this->typeMap as $name => $type) {
            if ($type instanceof ScalarType) {
                continue; // built-ins
            }

            if ($type instanceof ObjectType) {
                $sdl .= "type {$name} {\n";
                foreach ($type->getFields() as $field) {
                    $argsStr = '';
                    $args = $field->getArguments();
                    if (!empty($args)) {
                        $argParts = [];
                        foreach ($args as $arg) {
                            $argParts[] = "{$arg->getName()}: {$arg->getType()->toString()}";
                        }
                        $argsStr = '(' . implode(', ', $argParts) . ')';
                    }
                    $sdl .= "  {$field->getName()}{$argsStr}: {$field->getType()->toString()}\n";
                }
                $sdl .= "}\n\n";
            } elseif ($type instanceof InputObjectType) {
                $sdl .= "input {$name} {\n";
                foreach ($type->getFields() as $field) {
                    $sdl .= "  {$field->getName()}: {$field->getType()->toString()}\n";
                }
                $sdl .= "}\n\n";
            } elseif ($type instanceof EnumType) {
                $sdl .= "enum {$name} {\n";
                foreach (array_keys($type->getValues()) as $val) {
                    $sdl .= "  {$val}\n";
                }
                $sdl .= "}\n\n";
            }
        }

        return trim($sdl);
    }
}
