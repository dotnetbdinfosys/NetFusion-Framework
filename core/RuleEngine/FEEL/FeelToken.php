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

namespace NetFusion\Core\RuleEngine\FEEL;

class FeelToken
{
    public const T_NUMBER = 'NUMBER';
    public const T_STRING = 'STRING';
    public const T_BOOLEAN = 'BOOLEAN';
    public const T_NULL = 'NULL';
    public const T_IDENTIFIER = 'IDENTIFIER';
    public const T_OPERATOR = 'OPERATOR';
    public const T_COMPARISON = 'COMPARISON';
    public const T_RANGE_OPEN = 'RANGE_OPEN';
    public const T_RANGE_CLOSE = 'RANGE_CLOSE';
    public const T_DOT_DOT = 'DOT_DOT';
    public const T_COMMA = 'COMMA';
    public const T_LPAREN = 'LPAREN';
    public const T_RPAREN = 'RPAREN';
    public const T_DASH = 'DASH';
    public const T_KEYWORD = 'KEYWORD';
    public const T_EOF = 'EOF';

    public string $type;
    public mixed $value;
    public int $position;

    public function __construct(string $type, mixed $value, int $position = 0)
    {
        $this->type = $type;
        $this->value = $value;
        $this->position = $position;
    }
}
