<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Types/Field.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Types
| Purpose   : Definition of a GraphQL Object or Interface field.
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

use NetFusion\Core\GraphQL\Contracts\ExecutionContextInterface;
use NetFusion\Core\GraphQL\Contracts\ResolverInterface;
use NetFusion\Core\GraphQL\Contracts\TypeInterface;

class Field
{
    /** @var array<string, Argument> */
    protected array $arguments = [];
    /** @var callable|ResolverInterface|null */
    protected mixed $resolver = null;

    /**
     * @param string                           $name
     * @param TypeInterface                    $type
     * @param string|null                      $description
     * @param array<Argument|array>            $arguments
     * @param callable|ResolverInterface|null $resolver
     * @param string|null                      $deprecationReason
     */
    public function __construct(
        protected string $name,
        protected TypeInterface $type,
        protected ?string $description = null,
        array $arguments = [],
        mixed $resolver = null,
        protected ?string $deprecationReason = null
    ) {
        $this->resolver = $resolver;
        foreach ($arguments as $key => $arg) {
            if ($arg instanceof Argument) {
                $this->arguments[$arg->getName()] = $arg;
            } elseif (is_array($arg)) {
                $argName = is_string($key) ? $key : ($arg['name'] ?? '');
                $this->arguments[$argName] = new Argument(
                    name: $argName,
                    type: $arg['type'],
                    description: $arg['description'] ?? null,
                    defaultValue: $arg['defaultValue'] ?? null,
                    hasDefaultValue: array_key_exists('defaultValue', $arg)
                );
            }
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): TypeInterface
    {
        return $this->type;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<string, Argument>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getArgument(string $name): ?Argument
    {
        return $this->arguments[$name] ?? null;
    }

    public function getResolver(): mixed
    {
        return $this->resolver;
    }

    public function isDeprecated(): bool
    {
        return $this->deprecationReason !== null;
    }

    public function getDeprecationReason(): ?string
    {
        return $this->deprecationReason;
    }

    /**
     * Resolve the field value using custom resolver or default property/array lookup.
     */
    public function resolve(mixed $source, array $args, mixed $context, ExecutionContextInterface $info): mixed
    {
        if ($this->resolver !== null) {
            if ($this->resolver instanceof ResolverInterface) {
                return $this->resolver->resolve($source, $args, $context, $info);
            }
            if (is_callable($this->resolver)) {
                return ($this->resolver)($source, $args, $context, $info);
            }
        }

        // Default property / array resolver
        $fieldName = $this->name;

        if (is_array($source)) {
            return $source[$fieldName] ?? null;
        }

        if (is_object($source)) {
            if (isset($source->{$fieldName})) {
                return $source->{$fieldName};
            }
            if (method_exists($source, $fieldName)) {
                return $source->{$fieldName}();
            }
            $getter = 'get' . ucfirst($fieldName);
            if (method_exists($source, $getter)) {
                return $source->{$getter}();
            }
        }

        return null;
    }
}
