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

namespace NetFusion\Core\Formal\Contracts;

use NetFusion\Core\Formal\Ast\Expression;
use NetFusion\Core\Formal\Ast\Term;
use NetFusion\Core\Formal\Enums\SolverStatus;
use NetFusion\Core\Formal\Smt\DpllTSolver;

/**
 * Hoare Logic Deductive Program Verifier.
 * Verifies Hoare triples {P} S {Q} using Weakest Precondition (WP) calculus.
 * Validates whether P => WP(S, Q) is a valid theorem by proving that (P ∧ ¬WP(S, Q)) is UNSAT.
 */
class HoareVerifier
{
    private DpllTSolver $solver;

    public function __construct(?DpllTSolver $solver = null)
    {
        $this->solver = $solver ?? new DpllTSolver();
    }

    /**
     * Verify Hoare triple {P} method {Q}.
     *
     * @return array{verified: bool, counterexample: ?array}
     */
    public function verifyContract(ContractSpec $contract, array $assignments = []): array
    {
        $this->solver->reset();

        // 1. Assert Precondition P
        $pre = $contract->getPreconditionExpression();
        if ($pre !== null) {
            $this->solver->assert($pre);
        }

        // 2. Assert variable assignments in method body
        foreach ($assignments as $var => $term) {
            if ($term instanceof Term) {
                // var == term
                $this->solver->assert(Expression::eq(new \NetFusion\Core\Formal\Ast\Variable($var, $term->getSort()), $term));
            }
        }

        // 3. Negate Postcondition Q: Prove P ∧ body ∧ ¬Q is UNSAT
        $post = $contract->getPostconditionExpression();
        if ($post !== null) {
            $this->solver->assert(Expression::not($post));
        }

        $status = $this->solver->checkSat();

        if ($status === SolverStatus::UNSAT) {
            // No counterexample exists => Method contract formally proved!
            return [
                'verified'        => true,
                'counterexample' => null,
            ];
        }

        // SAT => Counterexample found where precondition holds but postcondition fails!
        return [
            'verified'        => false,
            'counterexample' => $this->solver->getModel()?->toArray() ?? [],
        ];
    }
}
