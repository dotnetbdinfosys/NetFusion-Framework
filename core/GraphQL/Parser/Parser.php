<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Parser/Parser.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Parser
| Purpose   : Recursive descent AST Parser for GraphQL query documents.
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

require_once __DIR__ . '/AST.php';

use RuntimeException;

/**
 * Class Parser
 *
 * Parses GraphQL source string into a complete DocumentNode AST.
 *
 * @package NetFusion\Core\GraphQL\Parser
 */
class Parser
{
    protected Lexer $lexer;

    public function __construct(string $source)
    {
        $this->lexer = new Lexer($source);
        $this->lexer->advance(); // advance from SOF to first real token
    }

    /**
     * Static helper to parse source text.
     */
    public static function parse(string $source): DocumentNode
    {
        $parser = new self($source);
        return $parser->parseDocument();
    }

    /**
     * Parse entire document.
     */
    public function parseDocument(): DocumentNode
    {
        $definitions = [];

        while ($this->lexer->getToken()->kind !== Token::EOF) {
            $definitions[] = $this->parseDefinition();
        }

        return new DocumentNode($definitions);
    }

    /**
     * Parse a top-level definition.
     */
    protected function parseDefinition(): Node
    {
        $token = $this->lexer->getToken();

        // Shorthand query: `{ field }`
        if ($token->kind === Token::BRACE_L) {
            $selectionSet = $this->parseSelectionSet();
            return new OperationDefinitionNode(
                operation: 'query',
                name: null,
                variableDefinitions: [],
                directives: [],
                selectionSet: $selectionSet
            );
        }

        if ($token->kind === Token::NAME) {
            $value = $token->value;
            if ($value === 'query' || $value === 'mutation' || $value === 'subscription') {
                return $this->parseOperationDefinition();
            }

            if ($value === 'fragment') {
                return $this->parseFragmentDefinition();
            }
        }

        throw $this->syntaxError("Unexpected token {$token->kind} '{$token->value}' at definition level");
    }

    /**
     * Parse operation definition: query/mutation/subscription [name] [(vars)] [@directives] { selections }
     */
    protected function parseOperationDefinition(): OperationDefinitionNode
    {
        $opToken = $this->lexer->getToken();
        $operation = $opToken->value ?? 'query';
        $this->lexer->advance();

        $name = null;
        if ($this->lexer->getToken()->kind === Token::NAME) {
            $name = $this->lexer->getToken()->value;
            $this->lexer->advance();
        }

        $variableDefinitions = [];
        if ($this->lexer->getToken()->kind === Token::PAREN_L) {
            $variableDefinitions = $this->parseVariableDefinitions();
        }

        $directives = [];
        if ($this->lexer->getToken()->kind === Token::AT) {
            $directives = $this->parseDirectives();
        }

        $selectionSet = $this->parseSelectionSet();

        return new OperationDefinitionNode(
            operation: $operation,
            name: $name,
            variableDefinitions: $variableDefinitions,
            directives: $directives,
            selectionSet: $selectionSet
        );
    }

    /**
     * Parse variable definitions: ($var: Type = default, ...)
     *
     * @return array<VariableDefinitionNode>
     */
    protected function parseVariableDefinitions(): array
    {
        $this->expect(Token::PAREN_L);
        $defs = [];

        while ($this->lexer->getToken()->kind !== Token::PAREN_R && $this->lexer->getToken()->kind !== Token::EOF) {
            $this->expect(Token::DOLLAR);
            $varNameToken = $this->expect(Token::NAME);
            $this->expect(Token::COLON);
            $typeNode = $this->parseType();

            $defaultValue = null;
            if ($this->lexer->getToken()->kind === Token::EQUALS) {
                $this->lexer->advance();
                $defaultValue = $this->parseValue();
            }

            $defs[] = new VariableDefinitionNode($varNameToken->value, $typeNode, $defaultValue);
        }

        $this->expect(Token::PAREN_R);
        return $defs;
    }

    /**
     * Parse selection set: { field1, field2, ... }
     */
    protected function parseSelectionSet(): SelectionSetNode
    {
        $this->expect(Token::BRACE_L);
        $selections = [];

        while ($this->lexer->getToken()->kind !== Token::BRACE_R && $this->lexer->getToken()->kind !== Token::EOF) {
            $selections[] = $this->parseSelection();
        }

        $this->expect(Token::BRACE_R);
        return new SelectionSetNode($selections);
    }

    /**
     * Parse field, fragment spread, or inline fragment.
     */
    protected function parseSelection(): Node
    {
        $token = $this->lexer->getToken();

        if ($token->kind === Token::SPREAD) {
            $this->lexer->advance();
            $nextToken = $this->lexer->getToken();

            if ($nextToken->kind === Token::NAME && $nextToken->value === 'on') {
                // Inline Fragment: `... on User { field }`
                $this->lexer->advance();
                $typeCondition = $this->expect(Token::NAME)->value;
                $directives = [];
                if ($this->lexer->getToken()->kind === Token::AT) {
                    $directives = $this->parseDirectives();
                }
                $selectionSet = $this->parseSelectionSet();
                return new InlineFragmentNode($typeCondition, $directives, $selectionSet);
            }

            if ($nextToken->kind === Token::BRACE_L || $nextToken->kind === Token::AT) {
                // Untyped inline fragment `... @include(if: $bool) { ... }`
                $directives = [];
                if ($this->lexer->getToken()->kind === Token::AT) {
                    $directives = $this->parseDirectives();
                }
                $selectionSet = $this->parseSelectionSet();
                return new InlineFragmentNode(null, $directives, $selectionSet);
            }

            // Named Fragment Spread: `... userFields`
            $fragmentName = $this->expect(Token::NAME)->value;
            $directives = [];
            if ($this->lexer->getToken()->kind === Token::AT) {
                $directives = $this->parseDirectives();
            }
            return new FragmentSpreadNode($fragmentName, $directives);
        }

        return $this->parseField();
    }

    /**
     * Parse field: [alias:] name [(args)] [@directives] [{ selections }]
     */
    protected function parseField(): FieldNode
    {
        $nameOrAliasToken = $this->expect(Token::NAME);
        $alias = null;
        $name = $nameOrAliasToken->value;

        if ($this->lexer->getToken()->kind === Token::COLON) {
            $this->lexer->advance();
            $alias = $name;
            $name = $this->expect(Token::NAME)->value;
        }

        $arguments = [];
        if ($this->lexer->getToken()->kind === Token::PAREN_L) {
            $arguments = $this->parseArguments();
        }

        $directives = [];
        if ($this->lexer->getToken()->kind === Token::AT) {
            $directives = $this->parseDirectives();
        }

        $selectionSet = null;
        if ($this->lexer->getToken()->kind === Token::BRACE_L) {
            $selectionSet = $this->parseSelectionSet();
        }

        return new FieldNode($name, $alias, $arguments, $directives, $selectionSet);
    }

    /**
     * Parse field arguments: (id: 1, limit: 10)
     *
     * @return array<ArgumentNode>
     */
    protected function parseArguments(): array
    {
        $this->expect(Token::PAREN_L);
        $arguments = [];

        while ($this->lexer->getToken()->kind !== Token::PAREN_R && $this->lexer->getToken()->kind !== Token::EOF) {
            $nameToken = $this->expect(Token::NAME);
            $this->expect(Token::COLON);
            $value = $this->parseValue();
            $arguments[] = new ArgumentNode($nameToken->value, $value);
        }

        $this->expect(Token::PAREN_R);
        return $arguments;
    }

    /**
     * Parse directives: @include(if: true) @skip(if: false)
     *
     * @return array<DirectiveNode>
     */
    protected function parseDirectives(): array
    {
        $directives = [];

        while ($this->lexer->getToken()->kind === Token::AT) {
            $this->lexer->advance(); // skip @
            $nameToken = $this->expect(Token::NAME);
            $args = [];
            if ($this->lexer->getToken()->kind === Token::PAREN_L) {
                $args = $this->parseArguments();
            }
            $directives[] = new DirectiveNode($nameToken->value, $args);
        }

        return $directives;
    }

    /**
     * Parse Fragment definition: fragment name on Type { field }
     */
    protected function parseFragmentDefinition(): FragmentDefinitionNode
    {
        $this->lexer->advance(); // skip 'fragment'
        $nameToken = $this->expect(Token::NAME);
        $onToken = $this->expect(Token::NAME);
        if ($onToken->value !== 'on') {
            throw $this->syntaxError("Expected 'on' after fragment name");
        }
        $typeConditionToken = $this->expect(Token::NAME);

        $directives = [];
        if ($this->lexer->getToken()->kind === Token::AT) {
            $directives = $this->parseDirectives();
        }

        $selectionSet = $this->parseSelectionSet();
        return new FragmentDefinitionNode($nameToken->value, $typeConditionToken->value, $directives, $selectionSet);
    }

    /**
     * Parse literal / variable value.
     */
    protected function parseValue(): Node
    {
        $token = $this->lexer->getToken();

        switch ($token->kind) {
            case Token::DOLLAR:
                $this->lexer->advance();
                $nameToken = $this->expect(Token::NAME);
                return new VariableValueNode($nameToken->value);

            case Token::INT:
                $this->lexer->advance();
                return new IntValueNode((int) $token->value);

            case Token::FLOAT:
                $this->lexer->advance();
                return new FloatValueNode((float) $token->value);

            case Token::STRING:
            case Token::BLOCK_STRING:
                $this->lexer->advance();
                return new StringValueNode((string) $token->value);

            case Token::NAME:
                $val = $token->value;
                $this->lexer->advance();
                if ($val === 'true') {
                    return new BooleanValueNode(true);
                }
                if ($val === 'false') {
                    return new BooleanValueNode(false);
                }
                if ($val === 'null') {
                    return new NullValueNode();
                }
                return new EnumValueNode((string) $val);

            case Token::BRACKET_L:
                return $this->parseListValue();

            case Token::BRACE_L:
                return $this->parseObjectValue();

            default:
                throw $this->syntaxError("Unexpected value token {$token->kind} '{$token->value}'");
        }
    }

    /**
     * Parse list value: [1, 2, "three", true]
     */
    protected function parseListValue(): ListValueNode
    {
        $this->expect(Token::BRACKET_L);
        $values = [];

        while ($this->lexer->getToken()->kind !== Token::BRACKET_R && $this->lexer->getToken()->kind !== Token::EOF) {
            $values[] = $this->parseValue();
        }

        $this->expect(Token::BRACKET_R);
        return new ListValueNode($values);
    }

    /**
     * Parse object value: { key1: "value", key2: 123 }
     */
    protected function parseObjectValue(): ObjectValueNode
    {
        $this->expect(Token::BRACE_L);
        $fields = [];

        while ($this->lexer->getToken()->kind !== Token::BRACE_R && $this->lexer->getToken()->kind !== Token::EOF) {
            $nameToken = $this->expect(Token::NAME);
            $this->expect(Token::COLON);
            $value = $this->parseValue();
            $fields[] = new ObjectFieldNode($nameToken->value, $value);
        }

        $this->expect(Token::BRACE_R);
        return new ObjectValueNode($fields);
    }

    /**
     * Parse type reference: NamedType, [Type], Type!, [Type!]!
     */
    protected function parseType(): TypeNode
    {
        $type = null;

        if ($this->lexer->getToken()->kind === Token::BRACKET_L) {
            $this->lexer->advance();
            $innerType = $this->parseType();
            $this->expect(Token::BRACKET_R);
            $type = new ListTypeNode($innerType);
        } else {
            $nameToken = $this->expect(Token::NAME);
            $type = new NamedTypeNode($nameToken->value);
        }

        if ($this->lexer->getToken()->kind === Token::BANG) {
            $this->lexer->advance();
            $type = new NonNullTypeNode($type);
        }

        return $type;
    }

    /**
     * Expect current token kind and advance, or throw syntax error.
     */
    protected function expect(string $kind): Token
    {
        $token = $this->lexer->getToken();
        if ($token->kind !== $kind) {
            throw $this->syntaxError("Expected token '{$kind}', found '{$token->kind}' ('{$token->value}')");
        }
        $this->lexer->advance();
        return $token;
    }

    /**
     * Create syntax error exception.
     */
    protected function syntaxError(string $message): RuntimeException
    {
        $token = $this->lexer->getToken();
        return new RuntimeException(sprintf("GraphQL Syntax Error: %s at line %d, column %d", $message, $token->line, $token->column));
    }
}
