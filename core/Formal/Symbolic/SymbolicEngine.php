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

namespace NetFusion\Core\Formal\Symbolic;

use NetFusion\Core\Formal\Ast\Constant;
use NetFusion\Core\Formal\Ast\Expression;
use NetFusion\Core\Formal\Ast\Sort;
use NetFusion\Core\Formal\Enums\SolverStatus;
use NetFusion\Core\Formal\Smt\DpllTSolver;

/**
 * Symbolic Execution Engine.
 * Explores program execution paths symbolically, generates test cases (concolic),
 * and detects safety property violations (division by zero, assertion failures).
 */
class SymbolicEngine
{
    private DpllTSolver $solver;
    /** @var SymbolicState[] */
    private array $discoveredStates = [];
    /** @var array<array{type: string, path: string, witness: array}> */
    private array $detectedBugs = [];

    public function __construct(?DpllTSolver $solver = null)
    {
        $this->solver = $solver ?? new DpllTSolver();
    }

    public function createInitialState(array $varNames): SymbolicState
    {
        $vars = [];
        foreach ($varNames as $name) {
            $vars[$name] = SymbolicValue::ofVar($name);
        }
        return new SymbolicState($vars);
    }

    /**
     * Check if a path condition is feasible using the SMT solver.
     */
    public function isFeasible(PathCondition $pc): bool
    {
        $this->solver->reset();
        foreach ($pc->getConstraints() as $constraint) {
            $this->solver->assert($constraint);
        }
        return $this->solver->checkSat() === SolverStatus::SAT;
    }

    /**
     * Check if a division operation might trigger division by zero under path condition.
     */
    public function checkDivisionByZero(SymbolicState $state, SymbolicValue $divisor): bool
    {
        $this->solver->reset();
        foreach ($state->getPathCondition()->getConstraints() as $c) {
            $this->solver->assert($c);
        }

        // Assert divisor == 0
        $this->solver->assert(Expression::eq($divisor->getTerm(), Constant::int(0)));

        $status = $this->solver->checkSat();
        if ($status === SolverStatus::SAT) {
            $this->detectedBugs[] = [
                'type'    => 'DIVISION_BY_ZERO',
                'path'    => (string)$state->getPathCondition(),
                'witness' => $this->solver->getModel()?->toArray() ?? [],
            ];
            return true;
        }

        return false;
    }

    /**
     * Check if an assertion is violated under path condition.
     */
    public function checkAssertion(SymbolicState $state, Expression $assertion): bool
    {
        $this->solver->reset();
        foreach ($state->getPathCondition()->getConstraints() as $c) {
            $this->solver->assert($c);
        }

        // Violate assertion: not(assertion)
        $this->solver->assert(Expression::not($assertion));

        $status = $this->solver->checkSat();
        if ($status === SolverStatus::SAT) {
            $this->detectedBugs[] = [
                'type'    => 'ASSERTION_VIOLATION',
                'path'    => (string)$state->getPathCondition(),
                'witness' => $this->solver->getModel()?->toArray() ?? [],
            ];
            return true;
        }

        return false;
    }

    public function getDetectedBugs(): array
    {
        return $this->detectedBugs;
    }

    public function reset(): void
    {
        $this->discoveredStates = [];
        $this->detectedBugs = [];
        $this->solver->reset();
    }
}
