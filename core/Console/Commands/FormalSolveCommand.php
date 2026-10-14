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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Formal\FormalManager;

/**
 * SMT Formula Solver CLI Command.
 */
class FormalSolveCommand extends Command
{
    protected string $name = 'formal:solve';
    protected string $description = 'Solve SMT-LIB 2.6 formulas and queries using DPLL(T) solver';

    public function handle(): int
    {
        $script = $this->argument(0, '(declare-const x Int) (declare-const y Int) (assert (> (+ x y) 10)) (check-sat) (get-model)');
        $this->info("Parsing and solving SMT-LIB 2.6 script...");

        $mgr = $this->app->has('formal') ? $this->app->get('formal') : new FormalManager();
        $results = $mgr->parseScript($script);

        $this->line("Solver Results: " . json_encode($results, JSON_PRETTY_PRINT));
        return 0;
    }
}
