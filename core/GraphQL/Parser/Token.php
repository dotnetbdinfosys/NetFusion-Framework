<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Parser/Token.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Parser
| Purpose   : Lexical Token representation for GraphQL source documents.
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

/**
 * Class Token
 *
 * Represents a single token emitted by the GraphQL Lexer.
 *
 * @package NetFusion\Core\GraphQL\Parser
 */
class Token
{
    public const SOF          = '<SOF>';
    public const EOF          = '<EOF>';
    public const BANG         = '!';
    public const DOLLAR       = '$';
    public const PAREN_L      = '(';
    public const PAREN_R      = ')';
    public const SPREAD       = '...';
    public const COLON        = ':';
    public const EQUALS       = '=';
    public const AT           = '@';
    public const BRACKET_L    = '[';
    public const BRACKET_R    = ']';
    public const BRACE_L      = '{';
    public const PIPE         = '|';
    public const BRACE_R      = '}';
    public const NAME         = 'Name';
    public const INT          = 'Int';
    public const FLOAT        = 'Float';
    public const STRING       = 'String';
    public const BLOCK_STRING = 'BlockString';
    public const COMMENT      = 'Comment';

    public function __construct(
        public string $kind,
        public ?string $value,
        public int $line,
        public int $column,
        public int $start,
        public int $end
    ) {}

    public function __toString(): string
    {
        return sprintf('%s "%s" (%d:%d)', $this->kind, $this->value ?? '', $this->line, $this->column);
    }
}
