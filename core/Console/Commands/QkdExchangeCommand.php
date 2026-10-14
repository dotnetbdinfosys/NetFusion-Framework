<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/QkdExchangeCommand.php
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
use NetFusion\Core\Security\Quantum\Exceptions\EavesdroppingDetectedException;
use NetFusion\Core\Security\Quantum\QuantumKeyManager;

class QkdExchangeCommand extends Command
{
    protected string $signature = 'qkd:exchange {peer? : Peer node ID} {--protocol=bb84 : QKD Protocol (bb84/e91)} {--photons=256 : Photons/EPR pairs} {--eve=0.0 : Eavesdropper rate}';
    protected string $description = 'Execute a quantum key distribution exchange with a peer node';

    public function handle(): int
    {
        $peer      = (string)($this->argument(0) ?: 'satellite-ground-station-alpha');
        $protocol  = strtolower((string)($this->option('protocol') ?: 'bb84'));
        $photons   = (int)($this->option('photons') ?: 256);
        $eveRate   = (float)($this->option('eve') ?: 0.0);

        $mgr = $this->app->has('qkd')
            ? $this->app->make('qkd')
            : QuantumKeyManager::getInstance();

        try {
            if ($protocol === 'e91') {
                $res = $mgr->exchangeE91($peer, $photons, $eveRate);
                $this->info("=== E91 Entanglement-based QKD Exchange Completed ===");
                $this->line("Peer Node         : " . $peer);
                $this->line("Distilled Key     : " . substr($res['key_hex'], 0, 32) . '...');
                $this->line("Bell Parameter S  : " . $res['bell_parameter_s'] . " (> 2.0 quantum violation)");
                $this->line("Entanglement OK   : " . ($res['bell_violated'] ? 'YES' : 'NO'));
            } else {
                $res = $mgr->exchangeBb84($peer, $photons, $eveRate);
                $this->info("=== BB84 Quantum Key Distribution Completed ===");
                $this->line("Peer Node         : " . $peer);
                $this->line("Distilled Key     : " . substr($res['key_hex'], 0, 32) . '...');
                $this->line("QBER Error Rate   : " . ($res['qber'] * 100) . "%");
                $this->line("Sifting Efficiency: " . $res['sifting_efficiency'] . "%");
                $this->line("Photons Sent      : " . $res['photons_sent']);
                $this->line("Distilled Bytes   : " . $res['distilled_bytes'] . " bytes (256-bit AES/OTP key)");
            }
            return 0;
        } catch (EavesdroppingDetectedException $e) {
            $this->error("=== QKD Exchange Aborted ===");
            $this->error($e->getMessage());
            return 1;
        }
    }
}
