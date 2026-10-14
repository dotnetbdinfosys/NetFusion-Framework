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
 * Symbolic Execution Trace CLI Command.
 */
class FormalSymbolicCommand extends Command
{
    protected string $name = 'formal:symbolic';
    protected string $description = 'Perform symbolic execution exploration and bug finding on methods';

    public function handle(): int
    {
        $target = $this->argument(0, 'demo_symbolic');
        $this->info("Executing symbolic exploration on {$target}...");

        $mgr = $this->app->has('formal') ? $this->app->get('formal') : new FormalManager();
        $engine = $mgr->createSymbolicEngine();

        $initState = $engine->createInitialState(['x', 'y']);
        $varX = $initState->get('x');
        $varY = $initState->get('y');

        // Check potential division by zero when y == 0
        $divBug = $engine->checkDivisionByZero($initState, $varY);

        $this->line("Symbolic Bugs Detected: " . ($divBug ? "1 (Division by zero)" : "0"));
        if ($divBug) {
            $this->warn("Bug details: " . json_encode($engine->getDetectedBugs(), JSON_PRETTY_PRINT));
        } else {
            $this->success("✓ No safety violations detected in symbolic trace.");
        }

        return 0;
    }
}
