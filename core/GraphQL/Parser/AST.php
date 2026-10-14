<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Parser/AST.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Parser
| Purpose   : AST (Abstract Syntax Tree) Node hierarchy for GraphQL queries & SDL.
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

namespace NetFusion\Core\GraphQL\Parser;

abstract class Node
{
    public int $line = 1;
    public int $column = 1;

    abstract public function getKind(): string;
}

class DocumentNode extends Node
{
    /**
     * @param array<Node> $definitions
     */
    public function __construct(
        public array $definitions = []
    ) {}

    public function getKind(): string
    {
        return 'Document';
    }
}

class OperationDefinitionNode extends Node
{
    /**
     * @param 'query'|'mutation'|'subscription' $operation
     * @param string|null                      $name
     * @param array<VariableDefinitionNode>    $variableDefinitions
     * @param array<DirectiveNode>             $directives
     * @param SelectionSetNode                 $selectionSet
     */
    public function __construct(
        public string $operation = 'query',
        public ?string $name = null,
        public array $variableDefinitions = [],
        public array $directives = [],
        public ?SelectionSetNode $selectionSet = null
    ) {}

    public function getKind(): string
    {
        return 'OperationDefinition';
    }
}

class VariableDefinitionNode extends Node
{
    public function __construct(
        public string $variableName,
        public TypeNode $type,
        public ?Node $defaultValue = null
    ) {}

    public function getKind(): string
    {
        return 'VariableDefinition';
    }
}

class SelectionSetNode extends Node
{
    /**
     * @param array<FieldNode|InlineFragmentNode|FragmentSpreadNode> $selections
     */
    public function __construct(
        public array $selections = []
    ) {}

    public function getKind(): string
    {
        return 'SelectionSet';
    }
}

class FieldNode extends Node
{
    /**
     * @param string                  $name
     * @param string|null             $alias
     * @param array<ArgumentNode>     $arguments
     * @param array<DirectiveNode>    $directives
     * @param SelectionSetNode|null   $selectionSet
     */
    public function __construct(
        public string $name,
        public ?string $alias = null,
        public array $arguments = [],
        public array $directives = [],
        public ?SelectionSetNode $selectionSet = null
    ) {}

    public function getResponseKey(): string
    {
        return $this->alias ?? $this->name;
    }

    public function getKind(): string
    {
        return 'Field';
    }
}

class ArgumentNode extends Node
{
    public function __construct(
        public string $name,
        public Node $value
    ) {}

    public function getKind(): string
    {
        return 'Argument';
    }
}

class DirectiveNode extends Node
{
    /**
     * @param string              $name
     * @param array<ArgumentNode> $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments = []
    ) {}

    public function getKind(): string
    {
        return 'Directive';
    }
}

class InlineFragmentNode extends Node
{
    /**
     * @param string|null          $typeCondition
     * @param array<DirectiveNode> $directives
     * @param SelectionSetNode     $selectionSet
     */
    public function __construct(
        public ?string $typeCondition,
        public array $directives = [],
        public ?SelectionSetNode $selectionSet = null
    ) {}

    public function getKind(): string
    {
        return 'InlineFragment';
    }
}

class FragmentDefinitionNode extends Node
{
    /**
     * @param string               $name
     * @param string               $typeCondition
     * @param array<DirectiveNode> $directives
     * @param SelectionSetNode     $selectionSet
     */
    public function __construct(
        public string $name,
        public string $typeCondition,
        public array $directives = [],
        public ?SelectionSetNode $selectionSet = null
    ) {}

    public function getKind(): string
    {
        return 'FragmentDefinition';
    }
}

class FragmentSpreadNode extends Node
{
    /**
     * @param string               $name
     * @param array<DirectiveNode> $directives
     */
    public function __construct(
        public string $name,
        public array $directives = []
    ) {}

    public function getKind(): string
    {
        return 'FragmentSpread';
    }
}

// -------------------------------------------------------------
// Type Nodes
// -------------------------------------------------------------

abstract class TypeNode extends Node {}

class NamedTypeNode extends TypeNode
{
    public function __construct(
        public string $name
    ) {}

    public function getKind(): string
    {
        return 'NamedType';
    }
}

class ListTypeNode extends TypeNode
{
    public function __construct(
        public TypeNode $type
    ) {}

    public function getKind(): string
    {
        return 'ListType';
    }
}

class NonNullTypeNode extends TypeNode
{
    public function __construct(
        public TypeNode $type
    ) {}

    public function getKind(): string
    {
        return 'NonNullType';
    }
}

// -------------------------------------------------------------
// Value Nodes
// -------------------------------------------------------------

class VariableValueNode extends Node
{
    public function __construct(public string $name) {}
    public function getKind(): string { return 'Variable'; }
}

class IntValueNode extends Node
{
    public function __construct(public int $value) {}
    public function getKind(): string { return 'IntValue'; }
}

class FloatValueNode extends Node
{
    public function __construct(public float $value) {}
    public function getKind(): string { return 'FloatValue'; }
}

class StringValueNode extends Node
{
    public function __construct(public string $value) {}
    public function getKind(): string { return 'StringValue'; }
}

class BooleanValueNode extends Node
{
    public function __construct(public bool $value) {}
    public function getKind(): string { return 'BooleanValue'; }
}

class NullValueNode extends Node
{
    public function getKind(): string { return 'NullValue'; }
}

class EnumValueNode extends Node
{
    public function __construct(public string $value) {}
    public function getKind(): string { return 'EnumValue'; }
}

class ListValueNode extends Node
{
    /** @param array<Node> $values */
    public function __construct(public array $values = []) {}
    public function getKind(): string { return 'ListValue'; }
}

class ObjectValueNode extends Node
{
    /** @param array<ObjectFieldNode> $fields */
    public function __construct(public array $fields = []) {}
    public function getKind(): string { return 'ObjectValue'; }
}

class ObjectFieldNode extends Node
{
    public function __construct(
        public string $name,
        public Node $value
    ) {}

    public function getKind(): string
    {
        return 'ObjectField';
    }
}
