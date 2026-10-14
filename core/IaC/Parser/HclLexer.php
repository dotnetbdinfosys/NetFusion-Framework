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
 * Pure PHP lexical analyzer for HashiCorp Configuration Language (HCL).
 */
class HclLexer
{
    private string $source;
    private int $length;
    private int $cursor = 0;
    private int $line = 1;

    public function __construct(string $source)
    {
        $this->source = $source;
        $this->length = strlen($source);
    }

    /**
     * Tokenize the entire HCL source into an array of HclTokens.
     *
     * @return array<HclToken>
     */
    public function tokenize(): array
    {
        $tokens = [];

        while ($this->cursor < $this->length) {
            $char = $this->source[$this->cursor];

            // 1. Whitespace & Newlines
            if ($char === "\n") {
                $this->line++;
                $this->cursor++;
                continue;
            }

            if (ctype_space($char)) {
                $this->cursor++;
                continue;
            }

            // 2. Comments (# or // or /* ... */)
            if ($char === '#' || ($char === '/' && ($this->peek() === '/'))) {
                $this->skipLineComment();
                continue;
            }

            if ($char === '/' && $this->peek() === '*') {
                $this->skipBlockComment();
                continue;
            }

            // 3. Punctuation
            if ($char === '=') {
                $tokens[] = new HclToken(HclToken::T_EQUALS, '=', $this->line);
                $this->cursor++;
                continue;
            }

            if ($char === '{') {
                $tokens[] = new HclToken(HclToken::T_LBRACE, '{', $this->line);
                $this->cursor++;
                continue;
            }

            if ($char === '}') {
                $tokens[] = new HclToken(HclToken::T_RBRACE, '}', $this->line);
                $this->cursor++;
                continue;
            }

            if ($char === '[') {
                $tokens[] = new HclToken(HclToken::T_LBRACKET, '[', $this->line);
                $this->cursor++;
                continue;
            }

            if ($char === ']') {
                $tokens[] = new HclToken(HclToken::T_RBRACKET, ']', $this->line);
                $this->cursor++;
                continue;
            }

            if ($char === ',') {
                $tokens[] = new HclToken(HclToken::T_COMMA, ',', $this->line);
                $this->cursor++;
                continue;
            }

            // 4. Strings ("...")
            if ($char === '"') {
                $tokens[] = $this->scanString();
                continue;
            }

            // 5. Numbers
            if (ctype_digit($char) || ($char === '-' && ctype_digit($this->peek() ?? ''))) {
                $tokens[] = $this->scanNumber();
                continue;
            }

            // 6. Identifiers, keywords, booleans
            if (ctype_alpha($char) || $char === '_' || $char === '$') {
                $tokens[] = $this->scanIdentifier();
                continue;
            }

            // Unknown character, skip
            $this->cursor++;
        }

        $tokens[] = new HclToken(HclToken::T_EOF, null, $this->line);
        return $tokens;
    }

    private function peek(): ?string
    {
        return ($this->cursor + 1 < $this->length) ? $this->source[$this->cursor + 1] : null;
    }

    private function skipLineComment(): void
    {
        while ($this->cursor < $this->length && $this->source[$this->cursor] !== "\n") {
            $this->cursor++;
        }
    }

    private function skipBlockComment(): void
    {
        $this->cursor += 2; // skip /*
        while ($this->cursor < $this->length) {
            if ($this->source[$this->cursor] === "\n") {
                $this->line++;
            }
            if ($this->source[$this->cursor] === '*' && $this->peek() === '/') {
                $this->cursor += 2;
                return;
            }
            $this->cursor++;
        }
    }

    private function scanString(): HclToken
    {
        $startLine = $this->line;
        $this->cursor++; // Skip opening quote
        $str = '';

        while ($this->cursor < $this->length) {
            $c = $this->source[$this->cursor];
            if ($c === '\\') {
                $next = $this->peek();
                if ($next === '"' || $next === '\\') {
                    $str .= $next;
                    $this->cursor += 2;
                    continue;
                } elseif ($next === 'n') {
                    $str .= "\n";
                    $this->cursor += 2;
                    continue;
                } elseif ($next === 't') {
                    $str .= "\t";
                    $this->cursor += 2;
                    continue;
                }
            }

            if ($c === '"') {
                $this->cursor++; // Skip closing quote
                break;
            }

            if ($c === "\n") {
                $this->line++;
            }

            $str .= $c;
            $this->cursor++;
        }

        return new HclToken(HclToken::T_STRING, $str, $startLine);
    }

    private function scanNumber(): HclToken
    {
        $start = $this->cursor;
        if ($this->source[$this->cursor] === '-') {
            $this->cursor++;
        }

        $isFloat = false;
        while ($this->cursor < $this->length) {
            $c = $this->source[$this->cursor];
            if (ctype_digit($c)) {
                $this->cursor++;
            } elseif ($c === '.' && !$isFloat && ctype_digit($this->peek() ?? '')) {
                $isFloat = true;
                $this->cursor++;
            } else {
                break;
            }
        }

        $numStr = substr($this->source, $start, $this->cursor - $start);
        $val = $isFloat ? (float) $numStr : (int) $numStr;

        return new HclToken(HclToken::T_NUMBER, $val, $this->line);
    }

    private function scanIdentifier(): HclToken
    {
        $start = $this->cursor;
        while ($this->cursor < $this->length) {
            $c = $this->source[$this->cursor];
            if (ctype_alnum($c) || $c === '_' || $c === '-' || $c === '.' || $c === '$' || $c === '{' || $c === '}') {
                $this->cursor++;
            } else {
                break;
            }
        }

        $id = substr($this->source, $start, $this->cursor - $start);
        $lower = strtolower($id);

        if ($lower === 'true') {
            return new HclToken(HclToken::T_BOOLEAN, true, $this->line);
        }
        if ($lower === 'false') {
            return new HclToken(HclToken::T_BOOLEAN, false, $this->line);
        }

        return new HclToken(HclToken::T_IDENTIFIER, $id, $this->line);
    }
}
