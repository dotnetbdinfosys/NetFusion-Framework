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

use NetFusion\Core\RuleEngine\Exceptions\FeelSyntaxException;

class FeelLexer
{
    protected string $input;
    protected int $length;
    protected int $cursor = 0;

    /**
     * @return array<FeelToken>
     */
    public function tokenize(string $input): array
    {
        $this->input = trim($input);
        $this->length = strlen($this->input);
        $this->cursor = 0;

        $tokens = [];

        while ($this->cursor < $this->length) {
            $char = $this->input[$this->cursor];

            // 1. Whitespace
            if (ctype_space($char)) {
                $this->cursor++;
                continue;
            }

            // 2. Dash '-' as unary wildcard or minus
            if ($char === '-' && ($this->cursor === 0 && $this->length === 1)) {
                $tokens[] = new FeelToken(FeelToken::T_DASH, '-', $this->cursor++);
                continue;
            }

            // 3. String literals ("..." or '...')
            if ($char === '"' || $char === "'") {
                $tokens[] = $this->readString($char);
                continue;
            }

            // 4. Dot-Dot '..' (range operator)
            if ($char === '.' && $this->peek(1) === '.') {
                $tokens[] = new FeelToken(FeelToken::T_DOT_DOT, '..', $this->cursor);
                $this->cursor += 2;
                continue;
            }

            // 5. Numbers
            if (ctype_digit($char) || ($char === '.' && ctype_digit($this->peek(1)))) {
                $tokens[] = $this->readNumber();
                continue;
            }

            // 6. Multi-character comparisons (<=, >=, !=, ==)
            if (in_array(substr($this->input, $this->cursor, 2), ['<=', '>=', '!=', '=='])) {
                $op = substr($this->input, $this->cursor, 2);
                $tokens[] = new FeelToken(FeelToken::T_COMPARISON, $op, $this->cursor);
                $this->cursor += 2;
                continue;
            }

            // 7. Single-character comparisons (<, >, =)
            if (in_array($char, ['<', '>', '='])) {
                $tokens[] = new FeelToken(FeelToken::T_COMPARISON, $char, $this->cursor);
                $this->cursor++;
                continue;
            }

            // 8. Range brackets: '[', '(', ']', ')'
            if ($char === '[' || $char === '(') {
                $tokens[] = new FeelToken(FeelToken::T_RANGE_OPEN, $char, $this->cursor++);
                continue;
            }
            if ($char === ']' || $char === ')') {
                $tokens[] = new FeelToken(FeelToken::T_RANGE_CLOSE, $char, $this->cursor++);
                continue;
            }

            // 9. Comma
            if ($char === ',') {
                $tokens[] = new FeelToken(FeelToken::T_COMMA, ',', $this->cursor++);
                continue;
            }

            // 10. Arithmetic operators: +, -, *, /, %
            if (in_array($char, ['+', '-', '*', '/', '%'])) {
                $tokens[] = new FeelToken(FeelToken::T_OPERATOR, $char, $this->cursor++);
                continue;
            }

            // 11. Identifiers / Keywords (true, false, null, and, or, not, in, starts with, etc.)
            if (ctype_alpha($char) || $char === '_' || $char === '$') {
                $tokens[] = $this->readIdentifier();
                continue;
            }

            throw new FeelSyntaxException(
                sprintf("Unexpected character '%s' at position %d in expression: %s", $char, $this->cursor, $this->input)
            );
        }

        $tokens[] = new FeelToken(FeelToken::T_EOF, null, $this->cursor);
        return $tokens;
    }

    protected function readString(string $quote): FeelToken
    {
        $start = $this->cursor++;
        $value = '';

        while ($this->cursor < $this->length) {
            $char = $this->input[$this->cursor];
            if ($char === '\\') {
                $this->cursor++;
                if ($this->cursor < $this->length) {
                    $value .= $this->input[$this->cursor++];
                }
                continue;
            }
            if ($char === $quote) {
                $this->cursor++;
                return new FeelToken(FeelToken::T_STRING, $value, $start);
            }
            $value .= $char;
            $this->cursor++;
        }

        throw new FeelSyntaxException(sprintf("Unterminated string literal starting at position %d", $start));
    }

    protected function readNumber(): FeelToken
    {
        $start = $this->cursor;
        $hasDot = false;

        while ($this->cursor < $this->length) {
            $char = $this->input[$this->cursor];
            if ($char === '.') {
                if ($this->peek(1) === '.') {
                    // Range operator '..'
                    break;
                }
                if ($hasDot) {
                    break;
                }
                $hasDot = true;
                $this->cursor++;
            } elseif (ctype_digit($char)) {
                $this->cursor++;
            } else {
                break;
            }
        }

        $numStr = substr($this->input, $start, $this->cursor - $start);
        $value = $hasDot ? (float) $numStr : (int) $numStr;

        return new FeelToken(FeelToken::T_NUMBER, $value, $start);
    }

    protected function readIdentifier(): FeelToken
    {
        $start = $this->cursor;

        while ($this->cursor < $this->length) {
            $char = $this->input[$this->cursor];
            if (ctype_alnum($char) || $char === '_' || $char === '.' || $char === '-' || $char === '$') {
                $this->cursor++;
            } else {
                break;
            }
        }

        $ident = substr($this->input, $start, $this->cursor - $start);
        $lower = strtolower($ident);

        if ($lower === 'true') {
            return new FeelToken(FeelToken::T_BOOLEAN, true, $start);
        }
        if ($lower === 'false') {
            return new FeelToken(FeelToken::T_BOOLEAN, false, $start);
        }
        if ($lower === 'null') {
            return new FeelToken(FeelToken::T_NULL, null, $start);
        }
        if (in_array($lower, ['and', 'or', 'not', 'in', 'if', 'then', 'else'])) {
            return new FeelToken(FeelToken::T_KEYWORD, $lower, $start);
        }

        return new FeelToken(FeelToken::T_IDENTIFIER, $ident, $start);
    }

    protected function peek(int $offset = 0): ?string
    {
        $pos = $this->cursor + $offset;
        return $pos < $this->length ? $this->input[$pos] : null;
    }
}
