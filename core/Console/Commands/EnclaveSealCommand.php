<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/EnclaveSealCommand.php
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

class EnclaveSealCommand extends Command
{
    protected string $signature = 'enclave:seal {secret? : Sensitive secret string to seal} {--enclave=vault-enclave-01 : Target enclave ID}';
    protected string $description = 'Cryptographically seal secret data bound to hardware enclave measurement';

    public function handle(): int
    {
        $secret    = (string)($this->argument(0) ?: 'Confidential-Master-DB-Password-2026!');
        $enclaveId = (string)($this->option('enclave') ?: 'vault-enclave-01');

        $mgr = $this->app->has('enclave')
            ? $this->app->make('enclave')
            : EnclaveManager::getInstance();

        if ($mgr->getEnclave($enclaveId) === null) {
            $mgr->createEnclave($enclaveId, EnclaveType::INTEL_SGX);
        }

        $sealed = $mgr->seal($enclaveId, $secret);

        $this->info("=== Cryptographic Sealing Succeeded ===");
        $this->line("Enclave ID      : " . $enclaveId);
        $this->line("Measurement     : " . substr($sealed->getMeasurementDigest(), 0, 24) . '...');
        $this->line("Ciphertext (hex): " . substr($sealed->getCiphertextHex(), 0, 32) . '...');
        $this->line("Nonce (hex)     : " . $sealed->getNonceHex());
        $this->line("Tag (hex)       : " . $sealed->getTagHex());

        return 0;
    }
}
