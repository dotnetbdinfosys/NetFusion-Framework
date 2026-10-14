<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Parser/Lexer.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Parser
| Purpose   : Pure PHP GraphQL Lexer & Tokenizer conforming to GraphQL spec.
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

use RuntimeException;

/**
 * Class Lexer
 *
 * Scans GraphQL source strings and produces a stream of tokens with exact line and column coordinates.
 *
 * @package NetFusion\Core\GraphQL\Parser
 */
class Lexer
{
    protected string $source;
    protected int $length;
    protected int $position = 0;
    protected int $line = 1;
    protected int $lineStart = 0;
    protected Token $currentToken;

    public function __construct(string $source)
    {
        $this->source = $source;
        $this->length = strlen($source);
        $this->currentToken = new Token(Token::SOF, null, 1, 1, 0, 0);
    }

    /**
     * Get current token.
     */
    public function getToken(): Token
    {
        return $this->currentToken;
    }

    /**
     * Advance to the next token.
     */
    public function advance(): Token
    {
        $this->currentToken = $this->readNextToken();
        return $this->currentToken;
    }

    /**
     * Read the next token from source.
     */
    protected function readNextToken(): Token
    {
        $this->skipWhitespaceAndComments();

        if ($this->position >= $this->length) {
            $col = 1 + $this->position - $this->lineStart;
            return new Token(Token::EOF, null, $this->line, $col, $this->position, $this->position);
        }

        $char = $this->source[$this->position];
        $startPos = $this->position;
        $col = 1 + $this->position - $this->lineStart;
        $line = $this->line;

        // Punctuators
        switch ($char) {
            case '!':
                $this->position++;
                return new Token(Token::BANG, '!', $line, $col, $startPos, $this->position);
            case '$':
                $this->position++;
                return new Token(Token::DOLLAR, '$', $line, $col, $startPos, $this->position);
            case '(':
                $this->position++;
                return new Token(Token::PAREN_L, '(', $line, $col, $startPos, $this->position);
            case ')':
                $this->position++;
                return new Token(Token::PAREN_R, ')', $line, $col, $startPos, $this->position);
            case ':':
                $this->position++;
                return new Token(Token::COLON, ':', $line, $col, $startPos, $this->position);
            case '=':
                $this->position++;
                return new Token(Token::EQUALS, '=', $line, $col, $startPos, $this->position);
            case '@':
                $this->position++;
                return new Token(Token::AT, '@', $line, $col, $startPos, $this->position);
            case '[':
                $this->position++;
                return new Token(Token::BRACKET_L, '[', $line, $col, $startPos, $this->position);
            case ']':
                $this->position++;
                return new Token(Token::BRACKET_R, ']', $line, $col, $startPos, $this->position);
            case '{':
                $this->position++;
                return new Token(Token::BRACE_L, '{', $line, $col, $startPos, $this->position);
            case '|':
                $this->position++;
                return new Token(Token::PIPE, '|', $line, $col, $startPos, $this->position);
            case '}':
                $this->position++;
                return new Token(Token::BRACE_R, '}', $line, $col, $startPos, $this->position);
            case '.':
                if (substr($this->source, $this->position, 3) === '...') {
                    $this->position += 3;
                    return new Token(Token::SPREAD, '...', $line, $col, $startPos, $this->position);
                }
                throw new RuntimeException("Unexpected character '.' at line {$line}, col {$col}");
        }

        // Names (Identifiers)
        if (($char >= 'a' && $char <= 'z') || ($char >= 'A' && $char <= 'Z') || $char === '_') {
            return $this->readName($startPos, $line, $col);
        }

        // Numbers (-?[0-9]+)
        if (($char >= '0' && $char <= '9') || $char === '-') {
            return $this->readNumber($startPos, $line, $col);
        }

        // Strings ("..." or """...""")
        if ($char === '"') {
            return $this->readString($startPos, $line, $col);
        }

        throw new RuntimeException(sprintf("Unexpected character '%s' at line %d, col %d", $char, $line, $col));
    }

    /**
     * Skip whitespace, tabs, commas, newlines and comments.
     */
    protected function skipWhitespaceAndComments(): void
    {
        while ($this->position < $this->length) {
            $char = $this->source[$this->position];

            if ($char === ' ' || $char === "\t" || $char === ',') {
                $this->position++;
            } elseif ($char === "\r") {
                $this->position++;
                if ($this->position < $this->length && $this->source[$this->position] === "\n") {
                    $this->position++;
                }
                $this->line++;
                $this->lineStart = $this->position;
            } elseif ($char === "\n") {
                $this->position++;
                $this->line++;
                $this->lineStart = $this->position;
            } elseif ($char === '#') {
                // Comment, skip until end of line
                $this->position++;
                while ($this->position < $this->length && $this->source[$this->position] !== "\n" && $this->source[$this->position] !== "\r") {
                    $this->position++;
                }
            } else {
                break;
            }
        }
    }

    /**
     * Read an identifier name token.
     */
    protected function readName(int $startPos, int $line, int $col): Token
    {
        while ($this->position < $this->length) {
            $c = $this->source[$this->position];
            if (($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z') || ($c >= '0' && $c <= '9') || $c === '_') {
                $this->position++;
            } else {
                break;
            }
        }

        $value = substr($this->source, $startPos, $this->position - $startPos);
        return new Token(Token::NAME, $value, $line, $col, $startPos, $this->position);
    }

    /**
     * Read integer or float number token.
     */
    protected function readNumber(int $startPos, int $line, int $col): Token
    {
        $isFloat = false;

        if ($this->source[$this->position] === '-') {
            $this->position++;
        }

        while ($this->position < $this->length && $this->source[$this->position] >= '0' && $this->source[$this->position] <= '9') {
            $this->position++;
        }

        if ($this->position < $this->length && $this->source[$this->position] === '.') {
            $isFloat = true;
            $this->position++;
            while ($this->position < $this->length && $this->source[$this->position] >= '0' && $this->source[$this->position] <= '9') {
                $this->position++;
            }
        }

        if ($this->position < $this->length && ($this->source[$this->position] === 'e' || $this->source[$this->position] === 'E')) {
            $isFloat = true;
            $this->position++;
            if ($this->position < $this->length && ($this->source[$this->position] === '+' || $this->source[$this->position] === '-')) {
                $this->position++;
            }
            while ($this->position < $this->length && $this->source[$this->position] >= '0' && $this->source[$this->position] <= '9') {
                $this->position++;
            }
        }

        $value = substr($this->source, $startPos, $this->position - $startPos);
        $kind = $isFloat ? Token::FLOAT : Token::INT;

        return new Token($kind, $value, $line, $col, $startPos, $this->position);
    }

    /**
     * Read string token.
     */
    protected function readString(int $startPos, int $line, int $col): Token
    {
        if (substr($this->source, $this->position, 3) === '"""') {
            return $this->readBlockString($startPos, $line, $col);
        }

        $this->position++; // skip opening "
        $value = '';

        while ($this->position < $this->length) {
            $c = $this->source[$this->position];

            if ($c === '"') {
                $this->position++; // skip closing "
                return new Token(Token::STRING, $value, $line, $col, $startPos, $this->position);
            }

            if ($c === "\n" || $c === "\r") {
                throw new RuntimeException("Unterminated string at line {$line}, col {$col}");
            }

            if ($c === '\\') {
                $this->position++;
                if ($this->position >= $this->length) {
                    throw new RuntimeException("Unterminated escape sequence in string at line {$line}");
                }
                $escaped = $this->source[$this->position];
                $value .= match ($escaped) {
                    '"'     => '"',
                    '\\'    => '\\',
                    '/'     => '/',
                    'b'     => "\x08",
                    'f'     => "\x0C",
                    'n'     => "\n",
                    'r'     => "\r",
                    't'     => "\t",
                    'u'     => $this->readUnicodeEscape(),
                    default => $escaped,
                };
                $this->position++;
            } else {
                $value .= $c;
                $this->position++;
            }
        }

        throw new RuntimeException("Unterminated string starting at line {$line}, col {$col}");
    }

    /**
     * Read block string """...""".
     */
    protected function readBlockString(int $startPos, int $line, int $col): Token
    {
        $this->position += 3; // skip opening """
        $value = '';

        while ($this->position < $this->length) {
            if (substr($this->source, $this->position, 3) === '"""') {
                $this->position += 3;
                return new Token(Token::BLOCK_STRING, trim($value), $line, $col, $startPos, $this->position);
            }

            $c = $this->source[$this->position];
            if ($c === "\n") {
                $this->line++;
                $this->lineStart = $this->position + 1;
            }
            $value .= $c;
            $this->position++;
        }

        throw new RuntimeException("Unterminated block string starting at line {$line}, col {$col}");
    }

    /**
     * Read 4-hex digit unicode escape sequence.
     */
    protected function readUnicodeEscape(): string
    {
        $hex = substr($this->source, $this->position + 1, 4);
        if (strlen($hex) < 4 || !ctype_xdigit($hex)) {
            throw new RuntimeException("Invalid unicode escape sequence \\u{$hex}");
        }
        $this->position += 4;
        return mb_chr((int) hexdec($hex), 'UTF-8');
    }
}
