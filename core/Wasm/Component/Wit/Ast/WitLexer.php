<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/Wit/Ast/WitLexer.php
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

namespace NetFusion\Core\Wasm\Component\Wit\Ast;

use NetFusion\Core\Wasm\Component\Exceptions\WitParseException;

/**
 * Tokenizer / Lexer for WebAssembly Interface Type (WIT) specifications.
 */
class WitLexer
{
    public const T_PACKAGE    = 'PACKAGE';
    public const T_WORLD      = 'WORLD';
    public const T_INTERFACE  = 'INTERFACE';
    public const T_IMPORT     = 'IMPORT';
    public const T_EXPORT     = 'EXPORT';
    public const T_TYPE       = 'TYPE';
    public const T_RECORD     = 'RECORD';
    public const T_VARIANT    = 'VARIANT';
    public const T_ENUM       = 'ENUM';
    public const T_FLAGS      = 'FLAGS';
    public const T_RESOURCE   = 'RESOURCE';
    public const T_STATIC     = 'STATIC';
    public const T_FUNC       = 'FUNC';
    public const T_USE        = 'USE';

    public const T_IDENT      = 'IDENT';
    public const T_STRING     = 'STRING';
    public const T_INT        = 'INT';

    public const T_LBRACE     = '{';
    public const T_RBRACE     = '}';
    public const T_LPAREN     = '(';
    public const T_RPAREN     = ')';
    public const T_LANGLE     = '<';
    public const T_RANGLE     = '>';
    public const T_COLON      = ':';
    public const T_SEMICOLON  = ';';
    public const T_COMMA      = ',';
    public const T_ARROW      = '->';
    public const T_EQUAL      = '=';
    public const T_SLASH      = '/';
    public const T_AT         = '@';
    public const T_EOF        = 'EOF';

    private int $pos = 0;
    private int $length = 0;
    private int $line = 1;

    public function __construct(private string $source)
    {
        $this->length = strlen($source);
    }

    /**
     * @return array<int, array{type: string, value: string, line: int}>
     */
    public function tokenize(): array
    {
        $tokens = [];
        while ($this->pos < $this->length) {
            $char = $this->source[$this->pos];

            // Whitespace
            if ($char === "\n") {
                $this->line++;
                $this->pos++;
                continue;
            }
            if (ctype_space($char)) {
                $this->pos++;
                continue;
            }

            // Single line comment: // ...
            if ($char === '/' && $this->peek() === '/') {
                while ($this->pos < $this->length && $this->source[$this->pos] !== "\n") {
                    $this->pos++;
                }
                continue;
            }

            // Block comment: /* ... */
            if ($char === '/' && $this->peek() === '*') {
                $this->pos += 2;
                while ($this->pos < $this->length && !($this->source[$this->pos] === '*' && $this->peek() === '/')) {
                    if ($this->source[$this->pos] === "\n") {
                        $this->line++;
                    }
                    $this->pos++;
                }
                $this->pos += 2;
                continue;
            }

            // Symbols & Arrows
            if ($char === '-' && $this->peek() === '>') {
                $tokens[] = ['type' => self::T_ARROW, 'value' => '->', 'line' => $this->line];
                $this->pos += 2;
                continue;
            }

            if (in_array($char, ['{', '}', '(', ')', '<', '>', ':', ';', ',', '=', '/', '@'], true)) {
                $tokens[] = ['type' => $char, 'value' => $char, 'line' => $this->line];
                $this->pos++;
                continue;
            }

            // Strings: "..."
            if ($char === '"') {
                $tokens[] = $this->readString();
                continue;
            }

            // Numbers / SemVer versions
            if (ctype_digit($char)) {
                $tokens[] = $this->readNumber();
                continue;
            }

            if ($char === '.') {
                $tokens[] = ['type' => '.', 'value' => '.', 'line' => $this->line];
                $this->pos++;
                continue;
            }

            // Identifiers / Keywords / Package strings (e.g. wasi:cli/environment@0.2.0)
            if ($this->isIdentStart($char)) {
                $tokens[] = $this->readIdent();
                continue;
            }

            throw new WitParseException("Unexpected character '{$char}' at line {$this->line}");
        }

        $tokens[] = ['type' => self::T_EOF, 'value' => '', 'line' => $this->line];
        return $tokens;
    }

    private function peek(): ?string
    {
        return ($this->pos + 1 < $this->length) ? $this->source[$this->pos + 1] : null;
    }

    private function isIdentStart(string $char): bool
    {
        return ctype_alpha($char) || $char === '_' || $char === '%';
    }

    private function isIdentPart(string $char): bool
    {
        return ctype_alnum($char) || in_array($char, ['_', '-', '.'], true);
    }

    private function readIdent(): array
    {
        $start = $this->pos;
        while ($this->pos < $this->length && $this->isIdentPart($this->source[$this->pos])) {
            $this->pos++;
        }
        $val = substr($this->source, $start, $this->pos - $start);
        $type = match (strtolower($val)) {
            'package'   => self::T_PACKAGE,
            'world'     => self::T_WORLD,
            'interface' => self::T_INTERFACE,
            'import'    => self::T_IMPORT,
            'export'    => self::T_EXPORT,
            'type'      => self::T_TYPE,
            'record'    => self::T_RECORD,
            'variant'   => self::T_VARIANT,
            'enum'      => self::T_ENUM,
            'flags'     => self::T_FLAGS,
            'resource'  => self::T_RESOURCE,
            'static'    => self::T_STATIC,
            'func'      => self::T_FUNC,
            'use'       => self::T_USE,
            default     => self::T_IDENT,
        };

        return ['type' => $type, 'value' => $val, 'line' => $this->line];
    }

    private function readString(): array
    {
        $this->pos++; // skip open quote
        $start = $this->pos;
        while ($this->pos < $this->length && $this->source[$this->pos] !== '"') {
            if ($this->source[$this->pos] === "\n") {
                $this->line++;
            }
            $this->pos++;
        }
        $val = substr($this->source, $start, $this->pos - $start);
        $this->pos++; // skip closing quote
        return ['type' => self::T_STRING, 'value' => $val, 'line' => $this->line];
    }

    private function readNumber(): array
    {
        $start = $this->pos;
        while ($this->pos < $this->length && (ctype_digit($this->source[$this->pos]) || $this->source[$this->pos] === '.')) {
            $this->pos++;
        }
        $val = substr($this->source, $start, $this->pos - $start);
        return ['type' => self::T_INT, 'value' => $val, 'line' => $this->line];
    }
}
