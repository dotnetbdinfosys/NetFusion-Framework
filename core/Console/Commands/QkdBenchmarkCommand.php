<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/QkdBenchmarkCommand.php
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
use NetFusion\Core\Security\Quantum\QuantumKeyManager;

class QkdBenchmarkCommand extends Command
{
    protected string $signature = 'qkd:benchmark {--trials=5 : Number of benchmark rounds}';
    protected string $description = 'Benchmark BB84 and E91 quantum key generation throughput';

    public function handle(): int
    {
        $trials = (int)($this->option('trials') ?: 5);

        $mgr = $this->app->has('qkd')
            ? $this->app->make('qkd')
            : QuantumKeyManager::getInstance();

        $t0 = microtime(true);
        $totalBytes = 0;

        for ($i = 0; $i < $trials; $i++) {
            $res = $mgr->exchangeBb84("bench-node-{$i}", 256);
            $totalBytes += $res['distilled_bytes'];
        }

        $elapsed = round((microtime(true) - $t0) * 1000, 2);
        $bps = round(($totalBytes * 8) / max(0.001, $elapsed / 1000), 2);

        $this->info("=== QKD Key Distillation Benchmark ===");
        $this->line("Rounds Executed    : {$trials}");
        $this->line("Total Distilled Key: {$totalBytes} bytes (" . ($totalBytes * 8) . " bits)");
        $this->line("Elapsed Time       : {$elapsed} ms");
        $this->line("Throughput         : {$bps} bps (Quantum Key Generation Rate)");

        return 0;
    }
}
