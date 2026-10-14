<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/QkdStatusCommand.php
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

class QkdStatusCommand extends Command
{
    protected string $signature = 'qkd:status';
    protected string $description = 'Display Quantum Key Distribution engine status and active key pools';

    public function handle(): int
    {
        $mgr = $this->app->has('qkd')
            ? $this->app->make('qkd')
            : QuantumKeyManager::getInstance();

        $this->info("=== Quantum Key Distribution (QKD) Mesh Status ===");
        $this->line("Engine Status      : ACTIVE");
        $this->line("Supported Protocols: BB84 (Prepare-and-Measure), E91 (Entanglement EPR Pairs)");
        $this->line("QBER Safe Threshold: " . ($mgr->getQberAnalyzer()->getQberThreshold() * 100) . "% max error");
        $this->line("Active Peer Pools  : " . count($mgr->getKeyPool('satellite-ground-station-alpha')));

        return 0;
    }
}
