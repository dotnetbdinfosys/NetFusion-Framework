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

class CypherLexer
{
    protected const KEYWORDS = [
        'MATCH', 'WHERE', 'RETURN', 'CREATE', 'SET', 'DELETE', 'DETACH',
        'AND', 'OR', 'NOT', 'XOR', 'ORDER', 'BY', 'LIMIT', 'SKIP', 'ASC', 'DESC',
        'IN', 'CONTAINS', 'STARTS', 'WITH', 'ENDS', 'AS', 'IS',
        'TRUE', 'FALSE', 'NULL', 'DISTINCT',
        'COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'COLLECT',
    ];

    protected int $pos = 0;
    protected int $length = 0;

    public function tokenize(string $query): array
    {
        $this->pos = 0;
        $this->length = strlen($query);
        $tokens = [];

        while ($this->pos < $this->length) {
            $char = $query[$this->pos];

            // Skip whitespace
            if (ctype_space($char)) {
                $this->pos++;
                continue;
            }

            // Skip line comments //
            if ($char === '/' && $this->peek($query, 1) === '/') {
                $this->pos += 2;
                while ($this->pos < $this->length && $query[$this->pos] !== "\n" && $query[$this->pos] !== "\r") {
                    $this->pos++;
                }
                continue;
            }

            $startPos = $this->pos;

            // Two-character / Three-character symbols & operators
            if ($char === '<' && $this->peek($query, 1) === '-' && $this->peek($query, 2) === '[') {
                $tokens[] = new CypherToken(CypherToken::T_SYMBOL, '<-[', $startPos);
                $this->pos += 3;
                continue;
            }
            if ($char === ']' && $this->peek($query, 1) === '-' && $this->peek($query, 2) === '>') {
                $tokens[] = new CypherToken(CypherToken::T_SYMBOL, ']->', $startPos);
                $this->pos += 3;
                continue;
            }
            if ($char === '<' && $this->peek($query, 1) === '-') {
                $tokens[] = new CypherToken(CypherToken::T_SYMBOL, '<-', $startPos);
                $this->pos += 2;
                continue;
            }
            if ($char === '-' && $this->peek($query, 1) === '>') {
                $tokens[] = new CypherToken(CypherToken::T_SYMBOL, '->', $startPos);
                $this->pos += 2;
                continue;
            }
            if ($char === '-' && $this->peek($query, 1) === '[') {
                $tokens[] = new CypherToken(CypherToken::T_SYMBOL, '-[', $startPos);
                $this->pos += 2;
                continue;
            }
            if ($char === ']' && $this->peek($query, 1) === '-') {
                $tokens[] = new CypherToken(CypherToken::T_SYMBOL, ']-', $startPos);
                $this->pos += 2;
                continue;
            }
            if ($char === '.' && $this->peek($query, 1) === '.') {
                $tokens[] = new CypherToken(CypherToken::T_SYMBOL, '..', $startPos);
                $this->pos += 2;
                continue;
            }
            if (($char === '!' || $char === '<') && $this->peek($query, 1) === '=') {
                $tokens[] = new CypherToken(CypherToken::T_OPERATOR, '!=', $startPos);
                $this->pos += 2;
                continue;
            }
            if ($char === '<' && $this->peek($query, 1) === '>') {
                $tokens[] = new CypherToken(CypherToken::T_OPERATOR, '<>', $startPos);
                $this->pos += 2;
                continue;
            }
            if ($char === '>' && $this->peek($query, 1) === '=') {
                $tokens[] = new CypherToken(CypherToken::T_OPERATOR, '>=', $startPos);
                $this->pos += 2;
                continue;
            }
            if ($char === '<' && $this->peek($query, 1) === '=') {
                $tokens[] = new CypherToken(CypherToken::T_OPERATOR, '<=', $startPos);
                $this->pos += 2;
                continue;
            }

            // Single character symbols & operators
            if (strpos('()[]{}:,;.*+-=<>', $char) !== false) {
                $tokens[] = new CypherToken(CypherToken::T_SYMBOL, $char, $startPos);
                $this->pos++;
                continue;
            }

            // String literals ('...' or "...")
            if ($char === "'" || $char === '"') {
                $quote = $char;
                $this->pos++;
                $str = '';
                while ($this->pos < $this->length) {
                    $c = $query[$this->pos];
                    if ($c === '\\') {
                        $this->pos++;
                        if ($this->pos < $this->length) {
                            $escaped = $query[$this->pos];
                            $str .= match ($escaped) {
                                'n' => "\n",
                                'r' => "\r",
                                't' => "\t",
                                default => $escaped,
                            };
                        }
                    } elseif ($c === $quote) {
                        $this->pos++;
                        break;
                    } else {
                        $str .= $c;
                    }
                    $this->pos++;
                }
                $tokens[] = new CypherToken(CypherToken::T_STRING, $str, $startPos);
                continue;
            }

            // Numbers (integers & floats)
            if (ctype_digit($char) || ($char === '-' && $this->pos + 1 < $this->length && ctype_digit($query[$this->pos + 1]))) {
                $numStr = '';
                if ($char === '-') {
                    $numStr .= $char;
                    $this->pos++;
                }
                while ($this->pos < $this->length && (ctype_digit($query[$this->pos]) || $query[$this->pos] === '.')) {
                    if ($query[$this->pos] === '.' && $this->peek($query, 1) === '.') {
                        // Range dots '..'
                        break;
                    }
                    $numStr .= $query[$this->pos];
                    $this->pos++;
                }
                $val = str_contains($numStr, '.') ? (float)$numStr : (int)$numStr;
                $tokens[] = new CypherToken(CypherToken::T_NUMBER, $val, $startPos);
                continue;
            }

            // Identifiers, Keywords, Booleans
            if (ctype_alpha($char) || $char === '_' || $char === '$') {
                $ident = '';
                while ($this->pos < $this->length && (ctype_alnum($query[$this->pos]) || $query[$this->pos] === '_' || $query[$this->pos] === '$')) {
                    $ident .= $query[$this->pos];
                    $this->pos++;
                }

                $upper = strtoupper($ident);
                if ($upper === 'TRUE' || $upper === 'FALSE') {
                    $tokens[] = new CypherToken(CypherToken::T_BOOLEAN, $upper === 'TRUE', $startPos);
                } elseif ($upper === 'NULL') {
                    $tokens[] = new CypherToken(CypherToken::T_NULL, null, $startPos);
                } elseif (in_array($upper, self::KEYWORDS, true)) {
                    $tokens[] = new CypherToken(CypherToken::T_KEYWORD, $upper, $startPos);
                } else {
                    $tokens[] = new CypherToken(CypherToken::T_IDENTIFIER, $ident, $startPos);
                }
                continue;
            }

            throw new CypherParseException("Unexpected character '{$char}'", $this->pos);
        }

        $tokens[] = new CypherToken(CypherToken::T_EOF, null, $this->pos);
        return $tokens;
    }

    protected function peek(string $query, int $offset = 1): ?string
    {
        $target = $this->pos + $offset;
        return ($target < $this->length) ? $query[$target] : null;
    }
}
