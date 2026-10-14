<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/EnclaveAttestCommand.php
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

class EnclaveAttestCommand extends Command
{
    protected string $signature = 'enclave:attest {enclave_id? : Enclave identifier} {--type=intel_sgx : Enclave type} {--nonce= : Challenge nonce}';
    protected string $description = 'Generate and verify hardware remote attestation quote for an enclave';

    public function handle(): int
    {
        $enclaveId = (string)($this->argument(0) ?: 'vault-enclave-01');
        $typeName  = strtolower((string)($this->option('type') ?: 'intel_sgx'));
        $nonce     = (string)($this->option('nonce') ?: hash('sha256', (string)microtime(true)));

        $enclaveType = EnclaveType::tryFrom($typeName) ?? EnclaveType::INTEL_SGX;

        $mgr = $this->app->has('enclave')
            ? $this->app->make('enclave')
            : EnclaveManager::getInstance();

        if ($mgr->getEnclave($enclaveId) === null) {
            $mgr->createEnclave($enclaveId, $enclaveType);
        }

        $quote  = $mgr->attest($enclaveId, $nonce);
        $status = $mgr->verifyQuote($quote, expectedUserData: $nonce);

        $this->info("=== Hardware Remote Attestation Quote ===");
        $this->line("Quote ID        : " . $quote->getQuoteId());
        $this->line("Enclave ID      : " . $enclaveId);
        $this->line("Architecture    : " . strtoupper($quote->getEnclaveType()->value));
        $this->line("MRENCLAVE Hash  : " . substr($quote->getMeasurement()->getMrEnclave(), 0, 24) . '...');
        $this->line("MRSIGNER Hash   : " . substr($quote->getMeasurement()->getMrSigner(), 0, 24) . '...');
        $this->line("Verification    : " . strtoupper($status->value));

        return $status->isValid() ? 0 : 1;
    }
}
