<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Graph\Cypher;

use NetFusion\Core\Graph\Exceptions\CypherParseException;

class CypherParser
{
    protected array $tokens = [];
    protected int $index = 0;
    protected int $totalTokens = 0;

    public function parse(string $query): CypherAST
    {
        $lexer = new CypherLexer();
        $this->tokens = $lexer->tokenize($query);
        $this->index = 0;
        $this->totalTokens = count($this->tokens);

        $ast = new CypherAST();

        while (!$this->isAtEnd()) {
            $token = $this->peek();

            if ($token->isKeyword('MATCH')) {
                $this->consume();
                $ast->matchPatterns = $this->parsePathPatterns();
            } elseif ($token->isKeyword('CREATE')) {
                $this->consume();
                $ast->createPatterns = $this->parsePathPatterns();
            } elseif ($token->isKeyword('WHERE')) {
                $this->consume();
                $ast->whereExpression = $this->parseWhereClause();
            } elseif ($token->isKeyword('SET')) {
                $this->consume();
                $ast->setClauses = $this->parseSetClause();
            } elseif ($token->isKeyword('DETACH')) {
                $this->consume();
                if ($this->matchKeyword('DELETE')) {
                    $ast->detachDelete = true;
                    $ast->deleteVariables = $this->parseDeleteVariables();
                } else {
                    throw new CypherParseException("Expected 'DELETE' after 'DETACH'", $token->position);
                }
            } elseif ($token->isKeyword('DELETE')) {
                $this->consume();
                $ast->deleteVariables = $this->parseDeleteVariables();
            } elseif ($token->isKeyword('RETURN')) {
                $this->consume();
                if ($this->matchKeyword('DISTINCT')) {
                    $ast->distinct = true;
                }
                $ast->returnItems = $this->parseReturnItems();
            } elseif ($token->isKeyword('ORDER')) {
                $this->consume();
                if ($this->matchKeyword('BY')) {
                    $ast->orderByItems = $this->parseOrderByItems();
                } else {
                    throw new CypherParseException("Expected 'BY' after 'ORDER'", $token->position);
                }
            } elseif ($token->isKeyword('SKIP')) {
                $this->consume();
                $skipToken = $this->consume(CypherToken::T_NUMBER, "Expected integer after SKIP");
                $ast->skip = (int)$skipToken->value;
            } elseif ($token->isKeyword('LIMIT')) {
                $this->consume();
                $limitToken = $this->consume(CypherToken::T_NUMBER, "Expected integer after LIMIT");
                $ast->limit = (int)$limitToken->value;
            } elseif ($token->type === CypherToken::T_SYMBOL && $token->value === ';') {
                $this->consume();
            } else {
                throw new CypherParseException("Unexpected token '{$token->value}'", $token->position);
            }
        }

        return $ast;
    }

    /**
     * Parse comma-separated path patterns e.g. `(a)-[:REL]->(b), (c:User)`
     *
     * @return array<PathPattern>
     */
    protected function parsePathPatterns(): array
    {
        $patterns = [];
        do {
            $patterns[] = $this->parseSinglePathPattern();
        } while ($this->matchSymbol(','));

        return $patterns;
    }

    protected function parseSinglePathPattern(): PathPattern
    {
        $elements = [];

        // First node
        $elements[] = $this->parseNodePattern();

        // Following edges and nodes
        while ($this->isEdgeStart()) {
            $elements[] = $this->parseEdgePattern();
            $elements[] = $this->parseNodePattern();
        }

        return new PathPattern($elements);
    }

    protected function parseNodePattern(): NodePattern
    {
        $this->consumeSymbol('(', "Expected '(' at start of node pattern");

        $variable = null;
        $label = null;
        $properties = [];

        // Check variable
        if ($this->check(CypherToken::T_IDENTIFIER)) {
            $variable = $this->consume()->value;
        }

        // Check label e.g. `:User`
        if ($this->matchSymbol(':')) {
            $labelToken = $this->consume(CypherToken::T_IDENTIFIER, "Expected label name after ':'");
            $label = $labelToken->value;
        }

        // Check inline properties e.g. `{name: 'Alice', age: 30}`
        if ($this->matchSymbol('{')) {
            $properties = $this->parsePropertyMap();
            $this->consumeSymbol('}', "Expected '}' at end of property map");
        }

        $this->consumeSymbol(')', "Expected ')' at end of node pattern");

        return new NodePattern($variable, $label, $properties);
    }

    protected function isEdgeStart(): bool
    {
        if ($this->isAtEnd()) {
            return false;
        }
        $t = $this->peek();
        return $t->isSymbol('-') || $t->isSymbol('<-') || $t->isSymbol('-[') || $t->isSymbol('<-[');
    }

    protected function parseEdgePattern(): EdgePattern
    {
        $direction = '->';
        $variable = null;
        $type = null;
        $minHops = 1;
        $maxHops = 1;
        $properties = [];

        if ($this->matchSymbol('<-[')) {
            $direction = '<-';
            [$variable, $type, $minHops, $maxHops, $properties] = $this->parseEdgeBody();
            $this->consumeSymbol(']-', "Expected ']-' at end of incoming edge pattern");
        } elseif ($this->matchSymbol('<-')) {
            $direction = '<-';
            // Simple anonymous incoming edge
        } elseif ($this->matchSymbol('-[') || ($this->matchSymbol('-') && $this->matchSymbol('['))) {
            [$variable, $type, $minHops, $maxHops, $properties] = $this->parseEdgeBody();
            if ($this->matchSymbol(']->') || ($this->matchSymbol(']') && $this->matchSymbol('->'))) {
                $direction = '->';
            } elseif ($this->matchSymbol(']-') || ($this->matchSymbol(']') && $this->matchSymbol('-'))) {
                $direction = '--';
            } else {
                throw new CypherParseException("Expected ']->' or ']-' after edge body", $this->peek()->position);
            }
        } elseif ($this->matchSymbol('->')) {
            $direction = '->';
        } elseif ($this->matchSymbol('-')) {
            if ($this->matchSymbol('->')) {
                $direction = '->';
            } else {
                $direction = '--';
            }
        }

        return new EdgePattern($variable, $type, $direction, $minHops, $maxHops, $properties);
    }

    /**
     * Parses `var:TYPE*1..3 {prop: val}` inside `[...]`
     */
    protected function parseEdgeBody(): array
    {
        $variable = null;
        $type = null;
        $minHops = 1;
        $maxHops = 1;
        $properties = [];

        if ($this->check(CypherToken::T_IDENTIFIER)) {
            $variable = $this->consume()->value;
        }

        if ($this->matchSymbol(':')) {
            $typeToken = $this->consume(CypherToken::T_IDENTIFIER, "Expected relationship type name after ':'");
            $type = $typeToken->value;
        }

        // Variable length hops: `*`, `*1..3`, `*..5`, `*2`
        if ($this->matchSymbol('*')) {
            $minHops = 1;
            $maxHops = null; // Unbounded default

            if ($this->check(CypherToken::T_NUMBER)) {
                $minHops = (int)$this->consume()->value;
                $maxHops = $minHops;
            }

            if ($this->matchSymbol('..')) {
                $maxHops = null;
                if ($this->check(CypherToken::T_NUMBER)) {
                    $maxHops = (int)$this->consume()->value;
                }
            }
        }

        if ($this->matchSymbol('{')) {
            $properties = $this->parsePropertyMap();
            $this->consumeSymbol('}', "Expected '}' at end of property map");
        }

        return [$variable, $type, $minHops, $maxHops, $properties];
    }

    protected function parsePropertyMap(): array
    {
        $map = [];
        if ($this->checkSymbol('}')) {
            return $map;
        }

        do {
            $keyToken = $this->consume(CypherToken::T_IDENTIFIER, "Expected property key name");
            $this->consumeSymbol(':', "Expected ':' after property key");
            $val = $this->parseLiteralValue();
            $map[$keyToken->value] = $val;
        } while ($this->matchSymbol(','));

        return $map;
    }

    protected function parseLiteralValue(): mixed
    {
        $t = $this->peek();
        if ($t->type === CypherToken::T_STRING || $t->type === CypherToken::T_NUMBER || $t->type === CypherToken::T_BOOLEAN || $t->type === CypherToken::T_NULL) {
            return $this->consume()->value;
        }
        if ($this->matchSymbol('[')) {
            $list = [];
            if (!$this->checkSymbol(']')) {
                do {
                    $list[] = $this->parseLiteralValue();
                } while ($this->matchSymbol(','));
            }
            $this->consumeSymbol(']', "Expected ']' at end of array literal");
            return $list;
        }
        throw new CypherParseException("Expected literal value (string, number, boolean, array, or null), got '{$t->value}'", $t->position);
    }

    protected function parseWhereClause(): array
    {
        return $this->parseOrExpression();
    }

    protected function parseOrExpression(): array
    {
        $left = $this->parseAndExpression();

        while ($this->matchKeyword('OR')) {
            $right = $this->parseAndExpression();
            $left = [
                'type' => 'logical',
                'operator' => 'OR',
                'left' => $left,
                'right' => $right,
            ];
        }

        return $left;
    }

    protected function parseAndExpression(): array
    {
        $left = $this->parseComparisonExpression();

        while ($this->matchKeyword('AND')) {
            $right = $this->parseComparisonExpression();
            $left = [
                'type' => 'logical',
                'operator' => 'AND',
                'left' => $left,
                'right' => $right,
            ];
        }

        return $left;
    }

    protected function parseComparisonExpression(): array
    {
        if ($this->matchKeyword('NOT')) {
            $expr = $this->parseComparisonExpression();
            return [
                'type' => 'not',
                'expression' => $expr,
            ];
        }

        if ($this->matchSymbol('(')) {
            $expr = $this->parseWhereClause();
            $this->consumeSymbol(')', "Expected ')' after parenthesized expression");
            return $expr;
        }

        $left = $this->parsePropertyAccessOrValue();

        $token = $this->peek();
        $op = null;

        if ($token->isOperator('=') || $token->isOperator('!=') || $token->isOperator('<>') ||
            $token->isOperator('>') || $token->isOperator('<') || $token->isOperator('>=') || $token->isOperator('<=')) {
            $op = $this->consume()->value;
        } elseif ($this->matchKeyword('CONTAINS')) {
            $op = 'CONTAINS';
        } elseif ($this->matchKeyword('STARTS')) {
            $this->consumeKeyword('WITH', "Expected 'WITH' after 'STARTS'");
            $op = 'STARTS WITH';
        } elseif ($this->matchKeyword('ENDS')) {
            $this->consumeKeyword('WITH', "Expected 'WITH' after 'ENDS'");
            $op = 'ENDS WITH';
        } elseif ($this->matchKeyword('IN')) {
            $op = 'IN';
        }

        if ($op !== null) {
            $right = $this->parsePropertyAccessOrValue();
            return [
                'type' => 'binary',
                'operator' => $op,
                'left' => $left,
                'right' => $right,
            ];
        }

        return $left;
    }

    protected function parsePropertyAccessOrValue(): mixed
    {
        $token = $this->peek();

        // Check variable or property access e.g. `u` or `u.age`
        if ($token->type === CypherToken::T_IDENTIFIER) {
            $var = $this->consume()->value;
            if ($this->matchSymbol('.')) {
                $prop = $this->consume(CypherToken::T_IDENTIFIER, "Expected property name after '.'")->value;
                return ['type' => 'property_access', 'variable' => $var, 'property' => $prop];
            }
            return ['type' => 'variable', 'variable' => $var];
        }

        // Literals
        if ($token->type === CypherToken::T_STRING || $token->type === CypherToken::T_NUMBER ||
            $token->type === CypherToken::T_BOOLEAN || $token->type === CypherToken::T_NULL) {
            $val = $this->consume()->value;
            return ['type' => 'literal', 'value' => $val];
        }

        // Array literal for `IN [...]`
        if ($this->matchSymbol('[')) {
            $list = [];
            if (!$this->checkSymbol(']')) {
                do {
                    $list[] = $this->parsePropertyAccessOrValue();
                } while ($this->matchSymbol(','));
            }
            $this->consumeSymbol(']', "Expected ']' at end of list");
            return ['type' => 'list', 'elements' => $list];
        }

        throw new CypherParseException("Unexpected token in expression '{$token->value}'", $token->position);
    }

    protected function parseSetClause(): array
    {
        $clauses = [];
        do {
            $varToken = $this->consume(CypherToken::T_IDENTIFIER, "Expected variable name in SET clause");
            $var = $varToken->value;

            if ($this->matchSymbol(':')) {
                // Label addition: `SET u:Admin`
                $labelToken = $this->consume(CypherToken::T_IDENTIFIER, "Expected label name after ':'");
                $clauses[] = ['variable' => $var, 'label' => $labelToken->value];
            } elseif ($this->matchSymbol('.')) {
                // Property assignment: `SET u.age = 30`
                $propToken = $this->consume(CypherToken::T_IDENTIFIER, "Expected property name after '.'");
                $this->consumeSymbol('=', "Expected '=' in property assignment");
                $val = $this->parseLiteralValue();
                $clauses[] = ['variable' => $var, 'property' => $propToken->value, 'value' => $val];
            } else {
                throw new CypherParseException("Expected '.' or ':' after variable in SET clause", $this->peek()->position);
            }
        } while ($this->matchSymbol(','));

        return $clauses;
    }

    protected function parseDeleteVariables(): array
    {
        $vars = [];
        do {
            $varToken = $this->consume(CypherToken::T_IDENTIFIER, "Expected variable name to delete");
            $vars[] = $varToken->value;
        } while ($this->matchSymbol(','));

        return $vars;
    }

    protected function parseReturnItems(): array
    {
        $items = [];
        do {
            $item = $this->parseSingleReturnItem();
            $items[] = $item;
        } while ($this->matchSymbol(','));

        return $items;
    }

    protected function parseSingleReturnItem(): ReturnItem
    {
        $token = $this->peek();

        // Aggregation functions e.g. `COUNT(u)`, `SUM(u.salary)`, `AVG(u.age)`, `MIN(u.score)`, `MAX(u.score)`
        if ($token->isKeyword('COUNT') || $token->isKeyword('SUM') || $token->isKeyword('AVG') ||
            $token->isKeyword('MIN') || $token->isKeyword('MAX') || $token->isKeyword('COLLECT')) {
            $agg = strtoupper($this->consume()->value);
            $this->consumeSymbol('(', "Expected '(' after aggregation function");

            $var = null;
            $prop = null;
            if ($this->matchSymbol('*')) {
                $var = '*';
            } else {
                $var = $this->consume(CypherToken::T_IDENTIFIER, "Expected variable in aggregation")->value;
                if ($this->matchSymbol('.')) {
                    $prop = $this->consume(CypherToken::T_IDENTIFIER, "Expected property after '.'")->value;
                }
            }
            $this->consumeSymbol(')', "Expected ')' after aggregation argument");

            $alias = null;
            if ($this->matchKeyword('AS')) {
                $alias = $this->consume(CypherToken::T_IDENTIFIER, "Expected alias name after AS")->value;
            }

            $expr = $prop !== null ? "{$agg}({$var}.{$prop})" : "{$agg}({$var})";
            return new ReturnItem($expr, $var, $prop, $agg, $alias);
        }

        // Variable or property projection e.g. `u` or `u.name`
        if ($token->type === CypherToken::T_IDENTIFIER) {
            $var = $this->consume()->value;
            $prop = null;
            if ($this->matchSymbol('.')) {
                $prop = $this->consume(CypherToken::T_IDENTIFIER, "Expected property name after '.'")->value;
            }

            $alias = null;
            if ($this->matchKeyword('AS')) {
                $alias = $this->consume(CypherToken::T_IDENTIFIER, "Expected alias name after AS")->value;
            }

            $expr = $prop !== null ? "{$var}.{$prop}" : $var;
            return new ReturnItem($expr, $var, $prop, null, $alias);
        }

        throw new CypherParseException("Unexpected token in RETURN clause: '{$token->value}'", $token->position);
    }

    protected function parseOrderByItems(): array
    {
        $items = [];
        do {
            $var = $this->consume(CypherToken::T_IDENTIFIER, "Expected variable name in ORDER BY")->value;
            $prop = null;
            if ($this->matchSymbol('.')) {
                $prop = $this->consume(CypherToken::T_IDENTIFIER, "Expected property name after '.'")->value;
            }

            $expr = $prop !== null ? "{$var}.{$prop}" : $var;
            $dir = 'ASC';

            if ($this->matchKeyword('DESC')) {
                $dir = 'DESC';
            } elseif ($this->matchKeyword('ASC')) {
                $dir = 'ASC';
            }

            $items[] = new OrderByItem($expr, $dir);
        } while ($this->matchSymbol(','));

        return $items;
    }

    // -------------------------------------------------------------
    // Helper Token Methods
    // -------------------------------------------------------------

    protected function peek(): CypherToken
    {
        return $this->tokens[$this->index] ?? new CypherToken(CypherToken::T_EOF, null, 0);
    }

    protected function consume(?string $expectedType = null, string $errorMessage = ''): CypherToken
    {
        if ($this->isAtEnd()) {
            throw new CypherParseException($errorMessage ?: "Unexpected end of query", $this->peek()->position);
        }
        $t = $this->tokens[$this->index];
        if ($expectedType !== null && $t->type !== $expectedType) {
            throw new CypherParseException($errorMessage ?: "Expected {$expectedType} but found {$t->type} ('{$t->value}')", $t->position);
        }
        $this->index++;
        return $t;
    }

    protected function matchKeyword(string $kw): bool
    {
        if (!$this->isAtEnd() && $this->peek()->isKeyword($kw)) {
            $this->index++;
            return true;
        }
        return false;
    }

    protected function consumeKeyword(string $kw, string $errorMessage = ''): CypherToken
    {
        if (!$this->isAtEnd() && $this->peek()->isKeyword($kw)) {
            return $this->tokens[$this->index++];
        }
        throw new CypherParseException($errorMessage ?: "Expected keyword '{$kw}'", $this->peek()->position);
    }

    protected function matchSymbol(string $sym): bool
    {
        if (!$this->isAtEnd() && $this->peek()->isSymbol($sym)) {
            $this->index++;
            return true;
        }
        return false;
    }

    protected function consumeSymbol(string $sym, string $errorMessage = ''): CypherToken
    {
        if (!$this->isAtEnd() && $this->peek()->isSymbol($sym)) {
            return $this->tokens[$this->index++];
        }
        throw new CypherParseException($errorMessage ?: "Expected symbol '{$sym}'", $this->peek()->position);
    }

    protected function checkSymbol(string $sym): bool
    {
        return !$this->isAtEnd() && $this->peek()->isSymbol($sym);
    }

    protected function check(string $type): bool
    {
        return !$this->isAtEnd() && $this->peek()->type === $type;
    }

    protected function isAtEnd(): bool
    {
        return $this->index >= $this->totalTokens || $this->peek()->type === CypherToken::T_EOF;
    }
}
