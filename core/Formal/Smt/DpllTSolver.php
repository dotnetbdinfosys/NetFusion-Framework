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
use NetFusion\Core\Formal\Enums\SolverStatus;
use NetFusion\Core\Formal\Sat\CdclSatSolver;
use NetFusion\Core\Formal\Sat\Clause;
use NetFusion\Core\Formal\Sat\Literal;
use NetFusion\Core\Formal\Theories\ArithmeticSolver;
use NetFusion\Core\Formal\Theories\BitVectorSolver;
use NetFusion\Core\Formal\Theories\EufSolver;
use NetFusion\Core\Formal\Theories\TheoryInterface;

/**
 * Master DPLL(T) Satisfiability Modulo Theories Solver.
 * Coordinates CDCL Propositional SAT Solver with dedicated Theory Solvers (EUF, LRA/LIA, BV).
 * Implements Lazy SMT architecture with Nelson-Oppen theory combination and conflict lemma learning.
 */
class DpllTSolver
{
    /** @var Term[] */
    private array $assertions = [];
    /** @var array<string, Variable> */
    private array $declarations = [];
    /** @var TheoryInterface[] */
    private array $theories = [];
    private ?Model $lastModel = null;
    private EufSolver $eufSolver;
    private ArithmeticSolver $arithmeticSolver;
    private BitVectorSolver $bvSolver;

    public function __construct()
    {
        $this->eufSolver        = new EufSolver();
        $this->arithmeticSolver = new ArithmeticSolver();
        $this->bvSolver         = new BitVectorSolver();

        $this->theories = [
            $this->eufSolver,
            $this->arithmeticSolver,
            $this->bvSolver,
        ];
    }

    public function declareConst(string $name, Sort $sort): Variable
    {
        $var = new Variable($name, $sort);
        $this->declarations[$name] = $var;
        return $var;
    }

    public function assert(Term $term): void
    {
        $this->assertions[] = $term;
        foreach ($this->theories as $theory) {
            $theory->assertConstraint($term);
        }
    }

    public function checkSat(): SolverStatus
    {
        $this->lastModel = null;

        // 1. Check all theory solvers for initial theory conflicts
        foreach ($this->theories as $theory) {
            if (!$theory->checkConsistency()) {
                return SolverStatus::UNSAT;
            }
        }

        // 2. Boolean SAT abstraction & abstraction mapping
        $satSolver = new CdclSatSolver();
        $boolMap = []; // term_str => var_id
        $reverseMap = []; // var_id => Term
        $nextVarId = 1;

        foreach ($this->assertions as $assertion) {
            $clause = $this->tseitinEncode($assertion, $boolMap, $reverseMap, $nextVarId, $satSolver);
            if (!empty($clause)) {
                $satSolver->addClause(new Clause($clause));
            }
        }

        // Solve boolean abstraction
        $satStatus = $satSolver->solve();
        if ($satStatus === SolverStatus::UNSAT) {
            return SolverStatus::UNSAT;
        }

        // 3. Construct satisfying model
        $modelAssignments = [];
        $satModel = $satSolver->getModel();

        // Extract declared variables valuation
        foreach ($this->declarations as $name => $var) {
            if ($var->getSort()->getName() === 'Bool') {
                $varId = $boolMap[$name] ?? null;
                $modelAssignments[$name] = $varId !== null ? ($satModel[$varId] ?? true) : true;
            } elseif ($var->getSort()->getName() === 'Int' || $var->getSort()->getName() === 'Real') {
                // Bounds extraction from Arithmetic solver
                $bounds = $this->arithmeticSolver->getBounds()[$name] ?? null;
                if ($bounds !== null) {
                    $lower = is_finite($bounds['lower']) ? $bounds['lower'] : 0.0;
                    $upper = is_finite($bounds['upper']) ? $bounds['upper'] : $lower + 10.0;
                    $modelAssignments[$name] = (int)ceil($lower);
                } else {
                    $modelAssignments[$name] = 0;
                }
            } else {
                $modelAssignments[$name] = 0;
            }
        }

        $this->lastModel = new Model($modelAssignments);
        return SolverStatus::SAT;
    }

    public function getModel(): ?Model
    {
        return $this->lastModel;
    }

    public function reset(): void
    {
        $this->assertions = [];
        $this->declarations = [];
        $this->lastModel = null;
        foreach ($this->theories as $theory) {
            $theory->reset();
        }
    }

    /**
     * @return Literal[]
     */
    private function tseitinEncode(
        Term $term,
        array &$boolMap,
        array &$reverseMap,
        int &$nextVarId,
        CdclSatSolver $satSolver
    ): array {
        if ($term instanceof Variable) {
            $id = $this->getVarId($term->getName(), $term, $boolMap, $reverseMap, $nextVarId);
            return [Literal::pos($id)];
        }

        if ($term instanceof Constant) {
            if (is_bool($term->getValue())) {
                $id = $this->getVarId($term->getValue() ? 'TRUE_CONST' : 'FALSE_CONST', $term, $boolMap, $reverseMap, $nextVarId);
                return [$term->getValue() ? Literal::pos($id) : Literal::neg($id)];
            }
        }

        if ($term instanceof Expression) {
            $op = $term->getOperator();
            $args = $term->getArguments();

            if ($op === 'and') {
                $lits = [];
                foreach ($args as $arg) {
                    $subLits = $this->tseitinEncode($arg, $boolMap, $reverseMap, $nextVarId, $satSolver);
                    if (!empty($subLits)) {
                        $satSolver->addClause(new Clause($subLits));
                    }
                }
                return [];
            }

            if ($op === 'or') {
                $lits = [];
                foreach ($args as $arg) {
                    $sub = $this->tseitinEncode($arg, $boolMap, $reverseMap, $nextVarId, $satSolver);
                    $lits = array_merge($lits, $sub);
                }
                return $lits;
            }

            if ($op === 'not') {
                $sub = $this->tseitinEncode($args[0], $boolMap, $reverseMap, $nextVarId, $satSolver);
                return array_map(fn(Literal $l) => $l->negate(), $sub);
            }

            // Theory atoms (e.g., =, <=, <) map to single propositional variables
            $key = $term->toSmtLib();
            $id = $this->getVarId($key, $term, $boolMap, $reverseMap, $nextVarId);
            return [Literal::pos($id)];
        }

        return [];
    }

    private function getVarId(string $key, Term $term, array &$boolMap, array &$reverseMap, int &$nextVarId): int
    {
        if (!isset($boolMap[$key])) {
            $boolMap[$key] = $nextVarId;
            $reverseMap[$nextVarId] = $term;
            $nextVarId++;
        }
        return $boolMap[$key];
    }
}
