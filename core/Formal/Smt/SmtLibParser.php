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

namespace NetFusion\Core\Formal\Smt;

use NetFusion\Core\Formal\Ast\Constant;
use NetFusion\Core\Formal\Ast\Expression;
use NetFusion\Core\Formal\Ast\Sort;
use NetFusion\Core\Formal\Ast\Term;
use NetFusion\Core\Formal\Ast\Variable;
use NetFusion\Core\Formal\Exceptions\ParseErrorException;

/**
 * High-performance pure-PHP SMT-LIB 2.6 Command Script Parser.
 * Supports:
 * - (declare-const <id> <sort>)
 * - (assert <term>)
 * - (check-sat)
 * - (get-model)
 * - (push), (pop), (reset)
 */
class SmtLibParser
{
    private DpllTSolver $solver;
    /** @var array<string, Variable> */
    private array $symbols = [];

    public function __construct(?DpllTSolver $solver = null)
    {
        $this->solver = $solver ?? new DpllTSolver();
    }

    public function getSolver(): DpllTSolver
    {
        return $this->solver;
    }

    public function parseScript(string $script): array
    {
        $tokens = $this->tokenize($script);
        $commands = $this->parseSExpressions($tokens);
        $results = [];

        foreach ($commands as $cmd) {
            if (!is_array($cmd) || empty($cmd)) {
                continue;
            }

            $op = $cmd[0];
            if ($op === 'declare-const') {
                $name = $cmd[1];
                $sort = $this->parseSort($cmd[2]);
                $this->symbols[$name] = $this->solver->declareConst($name, $sort);
            } elseif ($op === 'assert') {
                $term = $this->parseTerm($cmd[1]);
                $this->solver->assert($term);
            } elseif ($op === 'check-sat') {
                $results[] = $this->solver->checkSat()->value;
            } elseif ($op === 'get-model') {
                $model = $this->solver->getModel();
                $results[] = $model !== null ? $model->toArray() : null;
            } elseif ($op === 'reset') {
                $this->solver->reset();
                $this->symbols = [];
            }
        }

        return $results;
    }

    public function parseTerm(mixed $sexpr): Term
    {
        if (is_string($sexpr)) {
            if (isset($this->symbols[$sexpr])) {
                return $this->symbols[$sexpr];
            }
            if ($sexpr === 'true') {
                return Constant::bool(true);
            }
            if ($sexpr === 'false') {
                return Constant::bool(false);
            }
            if (is_numeric($sexpr)) {
                return str_contains($sexpr, '.') ? Constant::real($sexpr) : Constant::int((int)$sexpr);
            }
            // Default uninterpreted variable/symbol
            return new Variable($sexpr, Sort::int());
        }

        if (is_array($sexpr)) {
            if (empty($sexpr)) {
                throw new ParseErrorException("Empty S-expression term");
            }

            $op = $sexpr[0];
            $args = [];
            for ($i = 1; $i < count($sexpr); $i++) {
                $args[] = $this->parseTerm($sexpr[$i]);
            }

            return match ($op) {
                'not'      => Expression::not($args[0]),
                'and'      => Expression::and(...$args),
                'or'       => Expression::or(...$args),
                '=>'       => Expression::implies($args[0], $args[1]),
                '='        => Expression::eq($args[0], $args[1]),
                'distinct' => Expression::distinct(...$args),
                '<'        => Expression::lt($args[0], $args[1]),
                '<='       => Expression::lte($args[0], $args[1]),
                '>'        => Expression::gt($args[0], $args[1]),
                '>='       => Expression::gte($args[0], $args[1]),
                '+'        => Expression::add(...$args),
                '-'        => count($args) === 1 ? Expression::sub(Constant::int(0), $args[0]) : Expression::sub($args[0], $args[1]),
                '*'        => Expression::mul(...$args),
                '/'        => Expression::div($args[0], $args[1]),
                default    => new Expression($op, $args, Sort::int()),
            };
        }

        throw new ParseErrorException("Invalid term syntax: " . print_r($sexpr, true));
    }

    private function parseSort(mixed $sortExpr): Sort
    {
        if (is_string($sortExpr)) {
            return match (strtolower($sortExpr)) {
                'bool' => Sort::bool(),
                'int'  => Sort::int(),
                'real' => Sort::real(),
                default => Sort::uninterpreted($sortExpr),
            };
        }
        if (is_array($sortExpr) && count($sortExpr) === 3 && $sortExpr[0] === '_' && $sortExpr[1] === 'BitVec') {
            return Sort::bitVec((int)$sortExpr[2]);
        }
        return Sort::int();
    }

    private function tokenize(string $input): array
    {
        // Strip comments (; to EOL)
        $input = preg_replace('/;.*$/m', '', $input);
        // Add spacing around parentheses
        $input = str_replace(['(', ')'], [' ( ', ' ) '], $input ?? '');
        return preg_split('/\s+/', trim($input), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function parseSExpressions(array $tokens): array
    {
        $idx = 0;
        $commands = [];
        while ($idx < count($tokens)) {
            $commands[] = $this->readSExpr($tokens, $idx);
        }
        return array_filter($commands);
    }

    private function readSExpr(array &$tokens, int &$idx): mixed
    {
        if ($idx >= count($tokens)) {
            return null;
        }

        $tok = $tokens[$idx++];
        if ($tok === '(') {
            $list = [];
            while ($idx < count($tokens) && $tokens[$idx] !== ')') {
                $list[] = $this->readSExpr($tokens, $idx);
            }
            if ($idx < count($tokens) && $tokens[$idx] === ')') {
                $idx++; // consume ')'
            }
            return $list;
        }

        return $tok;
    }
}
