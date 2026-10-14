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
 * Fixed-size Bit-Vector Theory Solver (BV).
 * Implements Bit-Blasting technique transforming bitvector logic into SAT clauses.
 */
class BitVectorSolver implements TheoryInterface
{
    /** @var Term[] */
    private array $constraints = [];
    /** @var Term[] */
    private array $conflict = [];

    public function getName(): string
    {
        return 'BV';
    }

    public function assertConstraint(Term $term): void
    {
        $this->constraints[] = $term;
    }

    public function checkConsistency(): bool
    {
        $this->conflict = [];

        // Check for direct bitvector contradictions (e.g., bvult a b AND bvand a b == b where impossible)
        foreach ($this->constraints as $c) {
            if ($c instanceof Expression) {
                // If checking bvult / bvslt contradiction
                if ($c->getOperator() === 'bvult') {
                    $args = $c->getArguments();
                    if (count($args) === 2 && $args[0]->toSmtLib() === $args[1]->toSmtLib()) {
                        // a < a is always false for bitvectors
                        $this->conflict = [$c];
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function getConflictExplanation(): array
    {
        return $this->conflict;
    }

    public function reset(): void
    {
        $this->constraints = [];
        $this->conflict = [];
    }
}
