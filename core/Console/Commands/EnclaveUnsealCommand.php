<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/EnclaveUnsealCommand.php
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
use NetFusion\Core\Security\Enclave\EnclaveManager;
use NetFusion\Core\Security\Enclave\Enums\EnclaveType;
use NetFusion\Core\Security\Enclave\Sealing\SealedSecret;

class EnclaveUnsealCommand extends Command
{
    protected string $signature = 'enclave:unseal {--enclave=vault-enclave-01 : Target enclave ID} {--payload= : JSON serialized sealed secret}';
    protected string $description = 'Unseal and decrypt a hardware-sealed secret';

    public function handle(): int
    {
        $enclaveId = (string)($this->option('enclave') ?: 'vault-enclave-01');
        $payload   = (string)$this->option('payload');

        $mgr = $this->app->has('enclave')
            ? $this->app->make('enclave')
            : EnclaveManager::getInstance();

        if ($mgr->getEnclave($enclaveId) === null) {
            $mgr->createEnclave($enclaveId, EnclaveType::INTEL_SGX);
        }

        if (empty($payload)) {
            // Generate and unseal demo secret
            $sampleSecret = 'Auto-Sealed-Demovalue-998877';
            $sealed = $mgr->seal($enclaveId, $sampleSecret);
        } else {
            $sealed = SealedSecret::fromJson($payload);
        }

        $unsealed = $mgr->unseal($enclaveId, $sealed);

        $this->info("=== Cryptographic Unsealing Succeeded ===");
        $this->line("Enclave ID      : " . $enclaveId);
        $this->line("Measurement     : " . substr($sealed->getMeasurementDigest(), 0, 24) . '...');
        $this->line("Decrypted Secret: " . $unsealed);

        return 0;
    }
}
