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
use NetFusion\Core\Formal\Ast\Constant;
use NetFusion\Core\Formal\Ast\Expression;
use NetFusion\Core\Formal\Ast\Variable;
use NetFusion\Core\Formal\FormalManager;

/**
 * Formal Verification CLI Command.
 */
class FormalVerifyCommand extends Command
{
    protected string $name = 'formal:verify';
    protected string $description = 'Verify method formal contracts (Hoare Logic) and safety assertions';

    public function handle(): int
    {
        $target = $this->argument(0, 'sample_divide');
        $this->info("Executing Formal Verification for target: {$target}...");

        $mgr = $this->app->has('formal') ? $this->app->get('formal') : new FormalManager();

        // Sample verification: divide(a, b) requires b != 0, ensures res * b == a
        $contract = $mgr->newContract($target);
        $varA = Variable::int('a');
        $varB = Variable::int('b');
        $varRes = Variable::int('res');

        $contract->requires(Expression::distinct($varB, Constant::int(0)));
        $contract->ensures(Expression::eq(Expression::mul($varRes, $varB), $varA));

        $res = $mgr->verifyMethodContract($contract, [
            'res' => Expression::div($varA, $varB),
        ]);

        if ($res['verified']) {
            $this->success("✓ Formal Verification Succeeded: Method contract is mathematically proven!");
        } else {
            $this->warn("⚠ Verification Failed. Counterexample found: " . json_encode($res['counterexample']));
        }

        return 0;
    }
}
