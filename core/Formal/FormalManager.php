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

namespace NetFusion\Core\Formal;

use NetFusion\Core\Formal\Ast\Sort;
use NetFusion\Core\Formal\Ast\Term;
use NetFusion\Core\Formal\Ast\Variable;
use NetFusion\Core\Formal\Contracts\ContractSpec;
use NetFusion\Core\Formal\Contracts\HoareVerifier;
use NetFusion\Core\Formal\Enums\SolverStatus;
use NetFusion\Core\Formal\Smt\DpllTSolver;
use NetFusion\Core\Formal\Smt\Model;
use NetFusion\Core\Formal\Smt\SmtLibParser;
use NetFusion\Core\Formal\Symbolic\SymbolicEngine;
use NetFusion\Core\Formal\Symbolic\SymbolicState;

/**
 * Master Enterprise Formal Verification & SMT Solver Orchestration Facade.
 */
class FormalManager
{
    private DpllTSolver $solver;
    private SmtLibParser $parser;
    private SymbolicEngine $symbolicEngine;
    private HoareVerifier $hoareVerifier;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config         = $config;
        $this->solver         = new DpllTSolver();
        $this->parser         = new SmtLibParser($this->solver);
        $this->symbolicEngine = new SymbolicEngine($this->solver);
        $this->hoareVerifier  = new HoareVerifier($this->solver);
    }

    public function getSolver(): DpllTSolver
    {
        return $this->solver;
    }

    public function parseScript(string $smtScript): array
    {
        return $this->parser->parseScript($smtScript);
    }

    public function solveFormula(array $assertions, array $declarations = []): array
    {
        $this->solver->reset();
        foreach ($declarations as $name => $sort) {
            $this->solver->declareConst($name, $sort instanceof Sort ? $sort : Sort::int());
        }
        foreach ($assertions as $assert) {
            if ($assert instanceof Term) {
                $this->solver->assert($assert);
            }
        }

        $status = $this->solver->checkSat();
        return [
            'status' => $status->value,
            'model'  => $status === SolverStatus::SAT ? $this->solver->getModel()?->toArray() : null,
        ];
    }

    public function createSymbolicEngine(): SymbolicEngine
    {
        return $this->symbolicEngine;
    }

    public function verifyMethodContract(ContractSpec $contract, array $bodyAssignments = []): array
    {
        return $this->hoareVerifier->verifyContract($contract, $bodyAssignments);
    }

    public function newContract(string $methodName): ContractSpec
    {
        return new ContractSpec($methodName);
    }
}
