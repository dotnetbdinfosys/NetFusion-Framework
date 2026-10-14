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

class CypherToken
{
    public const T_KEYWORD     = 'KEYWORD';
    public const T_IDENTIFIER  = 'IDENTIFIER';
    public const T_STRING      = 'STRING';
    public const T_NUMBER      = 'NUMBER';
    public const T_BOOLEAN     = 'BOOLEAN';
    public const T_NULL        = 'NULL';
    public const T_OPERATOR    = 'OPERATOR';
    public const T_SYMBOL      = 'SYMBOL';
    public const T_EOF         = 'EOF';

    public function __construct(
        public readonly string $type,
        public readonly mixed $value,
        public readonly int $position
    ) {
    }

    public function isKeyword(string $kw): bool
    {
        return $this->type === self::T_KEYWORD && strcasecmp((string)$this->value, $kw) === 0;
    }

    public function isSymbol(string $sym): bool
    {
        return $this->type === self::T_SYMBOL && $this->value === $sym;
    }

    public function isOperator(string $op): bool
    {
        return ($this->type === self::T_OPERATOR || $this->type === self::T_SYMBOL) && $this->value === $op;
    }
}
