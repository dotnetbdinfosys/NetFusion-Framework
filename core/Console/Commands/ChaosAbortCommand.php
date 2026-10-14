<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Chaos\ChaosManager;

/**
 * Class ChaosAbortCommand
 *
 * CLI: php netfusion chaos:abort [--reason=...] [--initiated-by=...] [--resume]
 */
class ChaosAbortCommand extends Command
{
    protected string $name        = 'chaos:abort';
    protected string $description = 'Trigger global emergency kill switch to immediately halt all active chaos experiments';

    public function handle(array $args = []): int
    {
        $resume = isset($args['resume']) || in_array('--resume', $args, true) || $this->hasOption('resume');
        $reason = is_string($args['reason'] ?? null) ? $args['reason'] : (string) $this->option('reason', 'Operator invoked chaos:abort');
        $operator = is_string($args['initiated-by'] ?? null) ? $args['initiated-by'] : (string) $this->option('initiated-by', 'CLI');

        foreach ($args as $arg) {
            if (is_string($arg) && str_starts_with($arg, '--reason=')) {
                $reason = substr($arg, 9);
            }
            if (is_string($arg) && str_starts_with($arg, '--initiated-by=')) {
                $operator = substr($arg, 15);
            }
        }

        try {
            $chaos = ChaosManager::getInstance();

            if ($resume) {
                $chaos->resume();
                $this->info("Emergency kill switch has been DEACTIVATED. Normal chaos operations may resume.");
                return 0;
            }

            $chaos->abort($reason, $operator);
            $this->warn("EMERGENCY KILL SWITCH ACTIVATED!");
            echo "Reason       : {$reason}\n";
            echo "Initiated By : {$operator}\n";
            echo "All active fault injectors rolled back.\n";
            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to update emergency kill switch: " . $e->getMessage());
            return 1;
        }
    }
}
