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

use NetFusion\Core\RuleEngine\Contracts\FeelEvaluatorInterface;
use NetFusion\Core\RuleEngine\Exceptions\FeelSyntaxException;

class FeelEvaluator implements FeelEvaluatorInterface
{
    protected FeelLexer $lexer;

    public function __construct(?FeelLexer $lexer = null)
    {
        $this->lexer = $lexer ?? new FeelLexer();
    }

    /**
     * Evaluate a FEEL unary test against an input value.
     */
    public function evaluateUnaryTest(string $expression, mixed $inputValue, array $context = []): bool
    {
        $expr = trim($expression);

        // 1. Dash '-' or empty represents "any / don't care"
        if ($expr === '' || $expr === '-') {
            return true;
        }

        // 2. Handle negation: not(...)
        if (str_starts_with($expr, 'not(') && str_ends_with($expr, ')')) {
            $inner = substr($expr, 4, -1);
            return !$this->evaluateUnaryTest($inner, $inputValue, $context);
        }

        // 3. Handle comma-separated alternatives (e.g. '"GOLD", "PLATINUM"' or '< 10, >= 50')
        $parts = $this->splitTopLevelCommas($expr);
        if (count($parts) > 1) {
            foreach ($parts as $part) {
                if ($this->evaluateSingleUnaryTest(trim($part), $inputValue, $context)) {
                    return true;
                }
            }
            return false;
        }

        return $this->evaluateSingleUnaryTest($expr, $inputValue, $context);
    }

    /**
     * Evaluate a single unary test predicate.
     */
    protected function evaluateSingleUnaryTest(string $expr, mixed $inputValue, array $context = []): bool
    {
        // Check for range interval: [a..b], (a..b], [a..b), (a..b)
        if (preg_match('/^([\[\(])\s*([^.]+)\.\.([^\]\)]+)\s*([\]\)])$/', $expr, $matches)) {
            $leftBracket = $matches[1];
            $minVal = $this->evaluate(trim($matches[2]), $context);
            $maxVal = $this->evaluate(trim($matches[3]), $context);
            $rightBracket = $matches[4];

            $leftPass = ($leftBracket === '[') ? ($inputValue >= $minVal) : ($inputValue > $minVal);
            $rightPass = ($rightBracket === ']') ? ($inputValue <= $maxVal) : ($inputValue < $maxVal);

            return $leftPass && $rightPass;
        }

        // Check for comparison operator: <=, >=, !=, ==, <, >, =
        if (preg_match('/^(<=|>=|!=|==|<|>|=)\s*(.+)$/', $expr, $matches)) {
            $op = $matches[1];
            $targetVal = $this->evaluate(trim($matches[2]), $context);

            return match ($op) {
                '<' => $inputValue < $targetVal,
                '<=' => $inputValue <= $targetVal,
                '>' => $inputValue > $targetVal,
                '>=' => $inputValue >= $targetVal,
                '!=', '<>' => $inputValue != $targetVal,
                '=', '==' => $inputValue == $targetVal,
                default => false,
            };
        }

        // Literal equality or expression match
        $targetVal = $this->evaluate($expr, $context);
        return $inputValue == $targetVal;
    }

    /**
     * Evaluate a general FEEL expression.
     */
    public function evaluate(string $expression, array $context = []): mixed
    {
        $expr = trim($expression);
        if ($expr === '') {
            return null;
        }

        // 1. Literal booleans & null
        $lower = strtolower($expr);
        if ($lower === 'true') return true;
        if ($lower === 'false') return false;
        if ($lower === 'null') return null;

        // 2. Numeric literal
        if (is_numeric($expr)) {
            return str_contains($expr, '.') ? (float) $expr : (int) $expr;
        }

        // 3. String literal enclosed in quotes
        if ((str_starts_with($expr, '"') && str_ends_with($expr, '"')) ||
            (str_starts_with($expr, "'") && str_ends_with($expr, "'"))) {
            return substr($expr, 1, -1);
        }

        // 3b. Array literal [item1, item2, ...]
        if (str_starts_with($expr, '[') && str_ends_with($expr, ']') && !str_contains($expr, '..')) {
            $inner = trim(substr($expr, 1, -1));
            if ($inner === '') {
                return [];
            }
            $items = $this->splitTopLevelCommas($inner);
            return array_map(fn($item) => $this->evaluate(trim($item), $context), $items);
        }

        // 3c. Direct Variable lookup in context
        if (array_key_exists($expr, $context)) {
            return $context[$expr];
        }

        // 4. Conditional: if <cond> then <val1> else <val2>
        if (preg_match('/^if\s+(.+?)\s+then\s+(.+?)\s+else\s+(.+)$/i', $expr, $m)) {
            $cond = $this->evaluateBoolean($m[1], $context);
            return $cond ? $this->evaluate($m[2], $context) : $this->evaluate($m[3], $context);
        }

        // 5. Built-in string functions
        if (preg_match('/^starts\s+with\s*\((.*)\)$/is', $expr, $m)) {
            $args = $this->splitTopLevelCommas($m[1]);
            if (count($args) >= 2) {
                $str = (string) $this->evaluate(trim($args[0]), $context);
                $prefix = (string) $this->evaluate(trim($args[1]), $context);
                return str_starts_with($str, $prefix);
            }
        }
        if (preg_match('/^ends\s+with\s*\((.*)\)$/is', $expr, $m)) {
            $args = $this->splitTopLevelCommas($m[1]);
            if (count($args) >= 2) {
                $str = (string) $this->evaluate(trim($args[0]), $context);
                $suffix = (string) $this->evaluate(trim($args[1]), $context);
                return str_ends_with($str, $suffix);
            }
        }
        if (preg_match('/^contains\s*\((.*)\)$/is', $expr, $m)) {
            $args = $this->splitTopLevelCommas($m[1]);
            if (count($args) >= 2) {
                $str = (string) $this->evaluate(trim($args[0]), $context);
                $needle = (string) $this->evaluate(trim($args[1]), $context);
                return str_contains($str, $needle);
            }
        }
        if (preg_match('/^upper\s+case\s*\((.+?)\)$/i', $expr, $m)) {
            return strtoupper((string) $this->evaluate($m[1], $context));
        }
        if (preg_match('/^lower\s+case\s*\((.+?)\)$/i', $expr, $m)) {
            return strtolower((string) $this->evaluate($m[1], $context));
        }
        if (preg_match('/^abs\s*\((.+?)\)$/i', $expr, $m)) {
            return abs((float) $this->evaluate($m[1], $context));
        }
        if (preg_match('/^round\s*\((.*)\)$/is', $expr, $m)) {
            $args = $this->splitTopLevelCommas($m[1]);
            $val = (float) $this->evaluate(trim($args[0]), $context);
            $dec = isset($args[1]) ? (int) $this->evaluate(trim($args[1]), $context) : 0;
            return round($val, $dec);
        }

        // 6. List functions
        if (preg_match('/^list\s+contains\s*\((.*)\)$/is', $expr, $m)) {
            $args = $this->splitTopLevelCommas($m[1]);
            if (count($args) >= 2) {
                $list = $this->evaluate(trim($args[0]), $context);
                $listArr = is_array($list) ? $list : (array) $list;
                $item = $this->evaluate(trim($args[1]), $context);
                return in_array($item, $listArr, true);
            }
        }

        // 7. Binary arithmetic operations (+, -, *, /, %)
        foreach (['+', '-', '*', '/', '%'] as $op) {
            $parts = $this->splitByOperator($expr, $op);
            if ($parts !== null) {
                $leftRes = $this->evaluate($parts[0], $context);
                $rightRes = $this->evaluate($parts[1], $context);

                if (is_numeric($leftRes) && is_numeric($rightRes)) {
                    $left = (float) $leftRes;
                    $right = (float) $rightRes;
                    return match ($op) {
                        '+' => $left + $right,
                        '-' => $left - $right,
                        '*' => $left * $right,
                        '/' => $right != 0.0 ? ($left / $right) : 0.0,
                        '%' => $right != 0.0 ? ((int) $left % (int) $right) : 0,
                    };
                }
            }
        }

        // Nested dot notation (e.g. order.total)
        if (str_contains($expr, '.')) {
            $current = $context;
            $segments = explode('.', $expr);
            $found = true;
            foreach ($segments as $segment) {
                if (is_array($current) && array_key_exists($segment, $current)) {
                    $current = $current[$segment];
                } elseif (is_object($current) && isset($current->{$segment})) {
                    $current = $current->{$segment};
                } else {
                    $found = false;
                    break;
                }
            }
            if ($found) {
                return $current;
            }
        }

        return $expr;
    }

    protected function evaluateBoolean(string $expr, array $context): bool
    {
        $res = $this->evaluate($expr, $context);
        return (bool) $res;
    }

    /**
     * Split top-level comma separated items ignoring commas inside quotes and brackets.
     *
     * @return array<string>
     */
    protected function splitTopLevelCommas(string $input): array
    {
        $parts = [];
        $current = '';
        $inQuote = false;
        $quoteChar = '';
        $bracketDepth = 0;
        $len = strlen($input);

        for ($i = 0; $i < $len; $i++) {
            $ch = $input[$i];

            if (($ch === '"' || $ch === "'") && ($i === 0 || $input[$i - 1] !== '\\')) {
                if ($inQuote && $ch === $quoteChar) {
                    $inQuote = false;
                } elseif (!$inQuote) {
                    $inQuote = true;
                    $quoteChar = $ch;
                }
            }

            if (!$inQuote) {
                if ($ch === '[' || $ch === '(') $bracketDepth++;
                if ($ch === ']' || $ch === ')') $bracketDepth--;

                if ($ch === ',' && $bracketDepth === 0) {
                    $parts[] = $current;
                    $current = '';
                    continue;
                }
            }

            $current .= $ch;
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    /**
     * Split expression by top-level operator.
     *
     * @return array{string, string}|null
     */
    protected function splitByOperator(string $expr, string $op): ?array
    {
        // Avoid splitting on negative sign at the beginning
        if ($op === '-' && str_starts_with($expr, '-')) {
            return null;
        }

        $inQuote = false;
        $quoteChar = '';
        $parenDepth = 0;
        $len = strlen($expr);

        for ($i = 0; $i < $len; $i++) {
            $ch = $expr[$i];

            if (($ch === '"' || $ch === "'") && ($i === 0 || $expr[$i - 1] !== '\\')) {
                if ($inQuote && $ch === $quoteChar) {
                    $inQuote = false;
                } elseif (!$inQuote) {
                    $inQuote = true;
                    $quoteChar = $ch;
                }
            }

            if (!$inQuote) {
                if ($ch === '(') $parenDepth++;
                if ($ch === ')') $parenDepth--;

                if ($parenDepth === 0 && $ch === $op) {
                    // Make sure it's not part of '..' or '<=' or '>='
                    if ($op === '.' && ($i + 1 < $len && $expr[$i + 1] === '.')) continue;
                    if ($op === '=' && ($i > 0 && in_array($expr[$i - 1], ['<', '>', '!']))) continue;

                    $left = trim(substr($expr, 0, $i));
                    $right = trim(substr($expr, $i + 1));
                    if ($left !== '' && $right !== '') {
                        return [$left, $right];
                    }
                }
            }
        }

        return null;
    }
}
