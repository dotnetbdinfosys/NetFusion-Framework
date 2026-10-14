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

use NetFusion\Core\Formal\Ast\Expression;
use NetFusion\Core\Formal\Ast\Term;

/**
 * Theory of Equality and Uninterpreted Functions (EUF).
 * Solves equality constraints using Congruence Closure & Disjoint Set Union (DSU).
 * Enforces reflexivity, symmetry, transitivity, and congruence:
 * a = b ==> f(a) = f(b)
 */
class EufSolver implements TheoryInterface
{
    /** @var Term[] */
    private array $equalities = [];
    /** @var Term[] */
    private array $disequalities = [];
    /** @var array<string, string> term_str => representative */
    private array $parent = [];
    /** @var Term[] */
    private array $conflict = [];

    public function getName(): string
    {
        return 'EUF';
    }

    public function assertConstraint(Term $term): void
    {
        if ($term instanceof Expression) {
            if ($term->getOperator() === '=') {
                $this->equalities[] = $term;
            } elseif ($term->getOperator() === 'distinct' || ($term->getOperator() === 'not' && $term->getArguments()[0] instanceof Expression && $term->getArguments()[0]->getOperator() === '=')) {
                $this->disequalities[] = $term;
            }
        }
    }

    public function checkConsistency(): bool
    {
        $this->parent = [];
        $this->conflict = [];

        // 1. Process all equalities
        foreach ($this->equalities as $eq) {
            if ($eq instanceof Expression && count($eq->getArguments()) === 2) {
                $t1 = $eq->getArguments()[0]->toSmtLib();
                $t2 = $eq->getArguments()[1]->toSmtLib();
                $this->union($t1, $t2);
            }
        }

        // 2. Perform Congruence Closure for function applications
        $changed = true;
        while ($changed) {
            $changed = false;
            // Iterate over all known function applications
            $terms = array_map('strval', array_keys($this->parent));
            $count = count($terms);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $u = $terms[$i];
                    $v = $terms[$j];
                    if ($this->isCongruent($u, $v) && $this->find($u) !== $this->find($v)) {
                        $this->union($u, $v);
                        $changed = true;
                    }
                }
            }
        }

        // 3. Check for violations against disequalities
        foreach ($this->disequalities as $diseq) {
            if ($diseq instanceof Expression) {
                if ($diseq->getOperator() === 'distinct') {
                    $args = $diseq->getArguments();
                    if (count($args) === 2) {
                        $t1 = $args[0]->toSmtLib();
                        $t2 = $args[1]->toSmtLib();
                        if ($this->find($t1) === $this->find($t2)) {
                            $this->conflict = [$diseq];
                            return false;
                        }
                    }
                } elseif ($diseq->getOperator() === 'not') {
                    $inner = $diseq->getArguments()[0];
                    if ($inner instanceof Expression && $inner->getOperator() === '=' && count($inner->getArguments()) === 2) {
                        $t1 = $inner->getArguments()[0]->toSmtLib();
                        $t2 = $inner->getArguments()[1]->toSmtLib();
                        if ($this->find($t1) === $this->find($t2)) {
                            $this->conflict = [$diseq, $inner];
                            return false;
                        }
                    }
                }
            }
        }

        return true;
    }

    private function find(string $t): string
    {
        if (!isset($this->parent[$t])) {
            $this->parent[$t] = $t;
            return $t;
        }
        if ($this->parent[$t] !== $t) {
            $this->parent[$t] = $this->find($this->parent[$t]);
        }
        return $this->parent[$t];
    }

    private function union(string $t1, string $t2): void
    {
        $root1 = $this->find($t1);
        $root2 = $this->find($t2);
        if ($root1 !== $root2) {
            $this->parent[$root1] = $root2;
        }
    }

    private function isCongruent(string $u, string $v): bool
    {
        // Parse simple S-expr: (f arg1 arg2)
        if (str_starts_with($u, '(') && str_starts_with($v, '(')) {
            $partsU = explode(' ', trim($u, '()'));
            $partsV = explode(' ', trim($v, '()'));
            if (count($partsU) === count($partsV) && count($partsU) > 1) {
                if ($partsU[0] === $partsV[0]) { // Same function symbol
                    for ($k = 1; $k < count($partsU); $k++) {
                        if ($this->find($partsU[$k]) !== $this->find($partsV[$k])) {
                            return false;
                        }
                    }
                    return true;
                }
            }
        }
        return false;
    }

    public function areEqual(Term $t1, Term $t2): bool
    {
        return $this->find($t1->toSmtLib()) === $this->find($t2->toSmtLib());
    }

    public function getConflictExplanation(): array
    {
        return $this->conflict;
    }

    public function reset(): void
    {
        $this->equalities = [];
        $this->disequalities = [];
        $this->parent = [];
        $this->conflict = [];
    }
}
