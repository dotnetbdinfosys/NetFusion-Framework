<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Types/Directive.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Types
| Purpose   : GraphQL Directives (@include, @skip, @auth, @deprecated).
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

use NetFusion\Core\GraphQL\Contracts\DirectiveInterface;

class Directive implements DirectiveInterface
{
    /** @var array<string, Argument> */
    protected array $arguments = [];
    /** @var callable|null */
    protected $inclusionChecker = null;

    /**
     * @param string                $name
     * @param array<string>         $locations
     * @param array<Argument|array> $arguments
     * @param string|null           $description
     * @param callable|null         $inclusionChecker
     */
    public function __construct(
        protected string $name,
        protected array $locations = ['FIELD', 'FRAGMENT_SPREAD', 'INLINE_FRAGMENT'],
        array $arguments = [],
        protected ?string $description = null,
        ?callable $inclusionChecker = null
    ) {
        $this->inclusionChecker = $inclusionChecker;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getLocations(): array
    {
        return $this->locations;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function shouldInclude(array $args, mixed $context = null): bool
    {
        if ($this->inclusionChecker !== null) {
            return ($this->inclusionChecker)($args, $context);
        }

        if ($this->name === 'include') {
            return (bool) ($args['if'] ?? true);
        }

        if ($this->name === 'skip') {
            return !((bool) ($args['if'] ?? false));
        }

        return true;
    }

    // -------------------------------------------------------------
    // Standard Directives
    // -------------------------------------------------------------

    public static function includeDirective(): self
    {
        return new self(
            name: 'include',
            locations: ['FIELD', 'FRAGMENT_SPREAD', 'INLINE_FRAGMENT'],
            arguments: [
                'if' => [
                    'type'        => GraphQLType::nonNull(GraphQLType::boolean()),
                    'description' => 'Included when true.',
                ],
            ],
            description: 'Directs the executor to include this field or fragment only when the `if` argument is true.'
        );
    }

    public static function skipDirective(): self
    {
        return new self(
            name: 'skip',
            locations: ['FIELD', 'FRAGMENT_SPREAD', 'INLINE_FRAGMENT'],
            arguments: [
                'if' => [
                    'type'        => GraphQLType::nonNull(GraphQLType::boolean()),
                    'description' => 'Skipped when true.',
                ],
            ],
            description: 'Directs the executor to skip this field or fragment when the `if` argument is true.'
        );
    }

    public static function deprecatedDirective(): self
    {
        return new self(
            name: 'deprecated',
            locations: ['FIELD_DEFINITION', 'ENUM_VALUE'],
            arguments: [
                'reason' => [
                    'type'         => GraphQLType::string(),
                    'description'  => 'Explains why this element was deprecated.',
                    'defaultValue' => 'No longer supported',
                ],
            ],
            description: 'Marks an element of a GraphQL schema as no longer supported.'
        );
    }
}
