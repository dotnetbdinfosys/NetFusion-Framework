<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\IaC\Parser;

/**
 * Token emitted by the HCL Lexer.
 */
class HclToken
{
    public const T_IDENTIFIER = 'IDENTIFIER';
    public const T_STRING     = 'STRING';
    public const T_NUMBER     = 'NUMBER';
    public const T_BOOLEAN    = 'BOOLEAN';
    public const T_EQUALS     = 'EQUALS';
    public const T_LBRACE     = 'LBRACE';
    public const T_RBRACE     = 'RBRACE';
    public const T_LBRACKET   = 'LBRACKET';
    public const T_RBRACKET   = 'RBRACKET';
    public const T_COMMA      = 'COMMA';
    public const T_EOF        = 'EOF';

    public function __construct(
        public readonly string $type,
        public readonly mixed $value,
        public readonly int $line = 1
    ) {
    }
}
