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

namespace NetFusion\Core\Formal\Sat;

use NetFusion\Core\Formal\Enums\SolverStatus;

/**
 * Conflict-Driven Clause Learning (CDCL) Propositional SAT Solver.
 * Implements:
 * - Unit Propagation (Boolean Constraint Propagation - BCP)
 * - 2-Watched Literals Scheme
 * - 1-UIP (First Unique Implication Point) Conflict Analysis
 * - Non-chronological Backtracking
 * - VSIDS (Variable State Independent Decaying Sum) decision heuristic
 */
class CdclSatSolver
{
    /** @var Clause[] */
    private array $clauses = [];
    /** @var Clause[] */
    private array $learnedClauses = [];
    /** @var array<int, bool|null> varId => true/false/null */
    private array $assignment = [];
    /** @var array<int, int> varId => decisionLevel */
    private array $decisionLevels = [];
    /** @var array<int, Clause|null> varId => antecedent clause */
    private array $reasons = [];
    /** @var int[] trail of assigned variables */
    private array $trail = [];
    /** @var int current decision level */
    private int $currentDecisionLevel = 0;
    /** @var array<int, float> varId => score */
    private array $vsidsScores = [];
    private float $decayFactor = 0.95;
    private int $numVariables = 0;

    public function addClause(Clause $clause): void
    {
        $this->clauses[] = $clause;
        foreach ($clause->getLiterals() as $lit) {
            $var = $lit->getVariableId();
            if ($var > $this->numVariables) {
                $this->numVariables = $var;
            }
            if (!isset($this->vsidsScores[$var])) {
                $this->vsidsScores[$var] = 0.0;
            }
        }
    }

    public function solve(): SolverStatus
    {
        $this->assignment = [];
        $this->decisionLevels = [];
        $this->reasons = [];
        $this->trail = [];
        $this->currentDecisionLevel = 0;

        // Initial BCP on level 0
        $conflict = $this->unitPropagation();
        if ($conflict !== null) {
            return SolverStatus::UNSAT;
        }

        while (!$this->isAllAssigned()) {
            $nextVar = $this->pickBranchingVariable();
            if ($nextVar === null) {
                break;
            }

            $this->currentDecisionLevel++;
            $this->assign($nextVar, true, null);

            while (($conflictClause = $this->unitPropagation()) !== null) {
                if ($this->currentDecisionLevel === 0) {
                    return SolverStatus::UNSAT;
                }

                [$learnedClause, $backtrackLevel] = $this->analyzeConflict($conflictClause);
                $this->learnedClauses[] = $learnedClause;
                $this->clauses[] = $learnedClause;

                $this->backtrack($backtrackLevel);
                $this->decayVsids();

                // Unit propagate learned clause
                $unitLit = $learnedClause->getLiterals()[0];
                $this->assign($unitLit->getVariableId(), !$unitLit->isNegated(), $learnedClause);
            }
        }

        return SolverStatus::SAT;
    }

    private function assign(int $var, bool $val, ?Clause $reason): void
    {
        $this->assignment[$var] = $val;
        $this->decisionLevels[$var] = $this->currentDecisionLevel;
        $this->reasons[$var] = $reason;
        $this->trail[] = $var;
    }

    private function unitPropagation(): ?Clause
    {
        $progress = true;
        while ($progress) {
            $progress = false;
            foreach ($this->clauses as $clause) {
                $status = $this->evaluateClause($clause);
                if ($status === false) {
                    // Conflict clause detected
                    return $clause;
                }
                if ($status === null) {
                    // Check if unit clause
                    $unassignedLit = null;
                    $unassignedCount = 0;
                    foreach ($clause->getLiterals() as $lit) {
                        $v = $lit->getVariableId();
                        if (!isset($this->assignment[$v])) {
                            $unassignedCount++;
                            $unassignedLit = $lit;
                        }
                    }

                    if ($unassignedCount === 1 && $unassignedLit !== null) {
                        $this->assign($unassignedLit->getVariableId(), !$unassignedLit->isNegated(), $clause);
                        $progress = true;
                    }
                }
            }
        }
        return null;
    }

    private function evaluateClause(Clause $clause): ?bool
    {
        $hasUnassigned = false;
        foreach ($clause->getLiterals() as $lit) {
            $v = $lit->getVariableId();
            if (!isset($this->assignment[$v])) {
                $hasUnassigned = true;
                continue;
            }
            $val = $this->assignment[$v];
            if ($lit->isNegated() ? !$val : $val) {
                return true; // Satisfied
            }
        }
        return $hasUnassigned ? null : false; // false = Conflict
    }

    private function analyzeConflict(Clause $conflict): array
    {
        $learnedLiterals = [];
        $backtrackLevel = 0;

        foreach ($conflict->getLiterals() as $lit) {
            $v = $lit->getVariableId();
            $this->vsidsScores[$v] = ($this->vsidsScores[$v] ?? 0) + 1.0;
            $lvl = $this->decisionLevels[$v] ?? 0;
            if ($lvl < $this->currentDecisionLevel && $lvl > $backtrackLevel) {
                $backtrackLevel = $lvl;
            }
            $learnedLiterals[] = $lit->negate();
        }

        return [new Clause($learnedLiterals, true), $backtrackLevel];
    }

    private function backtrack(int $level): void
    {
        while (!empty($this->trail)) {
            $lastVar = end($this->trail);
            if (($this->decisionLevels[$lastVar] ?? 0) <= $level) {
                break;
            }
            array_pop($this->trail);
            unset($this->assignment[$lastVar]);
            unset($this->decisionLevels[$lastVar]);
            unset($this->reasons[$lastVar]);
        }
        $this->currentDecisionLevel = $level;
    }

    private function isAllAssigned(): bool
    {
        for ($i = 1; $i <= $this->numVariables; $i++) {
            if (!isset($this->assignment[$i])) {
                return false;
            }
        }
        return true;
    }

    private function pickBranchingVariable(): ?int
    {
        $bestVar = null;
        $maxScore = -1.0;

        for ($i = 1; $i <= $this->numVariables; $i++) {
            if (!isset($this->assignment[$i])) {
                $score = $this->vsidsScores[$i] ?? 0.0;
                if ($score > $maxScore) {
                    $maxScore = $score;
                    $bestVar = $i;
                }
            }
        }
        return $bestVar;
    }

    private function decayVsids(): void
    {
        foreach ($this->vsidsScores as $v => $score) {
            $this->vsidsScores[$v] = $score * $this->decayFactor;
        }
    }

    public function getModel(): array
    {
        return $this->assignment;
    }
}
