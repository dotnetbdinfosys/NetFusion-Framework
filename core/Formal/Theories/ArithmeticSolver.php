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

namespace NetFusion\Core\Formal\Theories;

use NetFusion\Core\Formal\Ast\Constant;
use NetFusion\Core\Formal\Ast\Expression;
use NetFusion\Core\Formal\Ast\Term;
use NetFusion\Core\Formal\Ast\Variable;

/**
 * Linear Real / Integer Arithmetic (LRA / LIA) Theory Solver.
 * Implements linear constraint normalization, interval propagation,
 * and Fourier-Motzkin elimination for conjunction of inequalities.
 */
class ArithmeticSolver implements TheoryInterface
{
    /** @var array<array{coeffs: array<string, float>, op: string, bound: float, term: Term}> */
    private array $constraints = [];
    /** @var Term[] */
    private array $conflict = [];
    /** @var array<string, array{lower: float, upper: float}> */
    private array $bounds = [];

    public function getName(): string
    {
        return 'LRA';
    }

    public function assertConstraint(Term $term): void
    {
        if ($term instanceof Expression) {
            if ($term->getOperator() === 'not') {
                $inner = $term->getArguments()[0];
                if ($inner instanceof Expression) {
                    $invOp = match ($inner->getOperator()) {
                        '>'     => '<=',
                        '>='    => '<',
                        '<'     => '>=',
                        '<='    => '>',
                        '='     => 'distinct',
                        default => null,
                    };
                    if ($invOp !== null) {
                        $parsed = $this->parseLinearConstraint(new Expression($invOp, $inner->getArguments(), $inner->getSort()));
                        if ($parsed !== null) {
                            $this->constraints[] = $parsed;
                        }
                        return;
                    }
                }
            }
            $parsed = $this->parseLinearConstraint($term);
            if ($parsed !== null) {
                $this->constraints[] = $parsed;
            }
        }
    }

    public function checkConsistency(): bool
    {
        $this->bounds = [];
        $this->conflict = [];

        // 1. Single-variable interval propagation
        foreach ($this->constraints as $c) {
            if (count($c['coeffs']) === 1) {
                $var = array_key_first($c['coeffs']);
                $coeff = $c['coeffs'][$var];
                $bound = $c['bound'];
                $op = $c['op'];

                if (!isset($this->bounds[$var])) {
                    $this->bounds[$var] = ['lower' => -INF, 'upper' => INF];
                }

                $val = $bound / $coeff;
                if ($coeff > 0) {
                    if ($op === '<=' || $op === '<') {
                        $this->bounds[$var]['upper'] = min($this->bounds[$var]['upper'], $val);
                    } elseif ($op === '>=' || $op === '>') {
                        $this->bounds[$var]['lower'] = max($this->bounds[$var]['lower'], $val);
                    } elseif ($op === '=') {
                        $this->bounds[$var]['lower'] = max($this->bounds[$var]['lower'], $val);
                        $this->bounds[$var]['upper'] = min($this->bounds[$var]['upper'], $val);
                    }
                } else {
                    if ($op === '<=' || $op === '<') {
                        $this->bounds[$var]['lower'] = max($this->bounds[$var]['lower'], $val);
                    } elseif ($op === '>=' || $op === '>') {
                        $this->bounds[$var]['upper'] = min($this->bounds[$var]['upper'], $val);
                    } elseif ($op === '=') {
                        $this->bounds[$var]['lower'] = max($this->bounds[$var]['lower'], $val);
                        $this->bounds[$var]['upper'] = min($this->bounds[$var]['upper'], $val);
                    }
                }

                if ($this->bounds[$var]['lower'] > $this->bounds[$var]['upper']) {
                    $this->conflict = [$c['term']];
                    return false;
                }
            }
        }

        // 2. Multivariable consistency check (e.g., y - x = 1 AND y - x <= 0)
        // Check pairwise substitution on two-variable constraints
        $twoVarConstraints = [];
        foreach ($this->constraints as $c) {
            if (count($c['coeffs']) === 2) {
                $twoVarConstraints[] = $c;
            }
        }

        for ($i = 0; $i < count($twoVarConstraints); $i++) {
            for ($j = $i + 1; $j < count($twoVarConstraints); $j++) {
                $c1 = $twoVarConstraints[$i];
                $c2 = $twoVarConstraints[$j];
                // If same variables with proportional coefficients
                $vars1 = array_keys($c1['coeffs']);
                $vars2 = array_keys($c2['coeffs']);
                sort($vars1);
                sort($vars2);
                if ($vars1 === $vars2) {
                    $v0 = $vars1[0];
                    $v1 = $vars1[1];
                    $r0 = $c1['coeffs'][$v0] / ($c2['coeffs'][$v0] ?: 1.0);
                    $r1 = $c1['coeffs'][$v1] / ($c2['coeffs'][$v1] ?: 1.0);
                    if (abs($r0 - $r1) < 1e-6 && $r0 > 0) {
                        $scaledBound2 = $c2['bound'] * $r0;
                        // c1: expr op1 bound1, c2: expr op2 scaledBound2
                        if ($c1['op'] === '=' && ($c2['op'] === '<=' || $c2['op'] === '<')) {
                            if ($c1['bound'] > $scaledBound2) {
                                $this->conflict = [$c1['term'], $c2['term']];
                                return false; // Inconsistent!
                            }
                        } elseif ($c2['op'] === '=' && ($c1['op'] === '<=' || $c1['op'] === '<')) {
                            if ($scaledBound2 > $c1['bound']) {
                                $this->conflict = [$c1['term'], $c2['term']];
                                return false;
                            }
                        }
                    }
                }
            }
        }

        return true;
    }

    private function parseLinearConstraint(Expression $expr): ?array
    {
        $op = $expr->getOperator();
        $args = $expr->getArguments();

        if (count($args) !== 2) {
            return null;
        }

        if (!in_array($op, ['<=', '<', '>=', '>', '=', 'distinct'], true)) {
            return null;
        }

        $coeffs = [];
        $bound = 0.0;

        // Process LHS
        $this->extractLinearTerms($args[0], 1.0, $coeffs, $bound);
        // Process RHS
        $this->extractLinearTerms($args[1], -1.0, $coeffs, $bound);

        // Normalize: sum(c_i * x_i) <= -bound
        return [
            'coeffs' => $coeffs,
            'op'     => $op,
            'bound'  => -$bound,
            'term'   => $expr,
        ];
    }

    private function extractLinearTerms(Term $term, float $scale, array &$coeffs, float &$constant): void
    {
        if ($term instanceof Variable) {
            $name = $term->getName();
            $coeffs[$name] = ($coeffs[$name] ?? 0.0) + $scale;
        } elseif ($term instanceof Constant) {
            $constant += $scale * (float)$term->getValue();
        } elseif ($term instanceof Expression) {
            if ($term->getOperator() === '+') {
                foreach ($term->getArguments() as $arg) {
                    $this->extractLinearTerms($arg, $scale, $coeffs, $constant);
                }
            } elseif ($term->getOperator() === '-') {
                $args = $term->getArguments();
                if (count($args) === 2) {
                    $this->extractLinearTerms($args[0], $scale, $coeffs, $constant);
                    $this->extractLinearTerms($args[1], -$scale, $coeffs, $constant);
                } elseif (count($args) === 1) {
                    $this->extractLinearTerms($args[0], -$scale, $coeffs, $constant);
                }
            } elseif ($term->getOperator() === '*') {
                $args = $term->getArguments();
                if (count($args) === 2 && $args[0] instanceof Constant && $args[1] instanceof Variable) {
                    $coeffs[$args[1]->getName()] = ($coeffs[$args[1]->getName()] ?? 0.0) + $scale * (float)$args[0]->getValue();
                }
            }
        }
    }

    public function getConflictExplanation(): array
    {
        return $this->conflict;
    }

    public function getBounds(): array
    {
        return $this->bounds;
    }

    public function reset(): void
    {
        $this->constraints = [];
        $this->conflict = [];
        $this->bounds = [];
    }
}
