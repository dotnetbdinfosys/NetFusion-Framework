<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Enclave/EnclaveManager.php
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

namespace NetFusion\Core\Security\Enclave;

use NetFusion\Core\Security\Enclave\Attestation\AttestationQuote;
use NetFusion\Core\Security\Enclave\Attestation\AttestationVerifier;
use NetFusion\Core\Security\Enclave\Attestation\PlatformMeasurement;
use NetFusion\Core\Security\Enclave\Enums\AttestationStatus;
use NetFusion\Core\Security\Enclave\Enums\EnclaveType;
use NetFusion\Core\Security\Enclave\Exceptions\EnclaveException;
use NetFusion\Core\Security\Enclave\Runtime\EnclaveContext;
use NetFusion\Core\Security\Enclave\Sealing\CryptographicSealer;
use NetFusion\Core\Security\Enclave\Sealing\SealedSecret;

/**
 * Enterprise Confidential Computing Enclave & Hardware Attestation Master Manager.
 */
class EnclaveManager
{
    private static ?self $instance = null;

    /** @var array<string, EnclaveContext> */
    private array $enclaves = [];

    private AttestationVerifier $verifier;
    private CryptographicSealer $sealer;

    public function __construct(array $config = [])
    {
        $hwSecret   = $config['hardware_secret'] ?? 'DotNetBD-Confidential-Hardware-Platform-Root-Key';
        $masterSeed = $config['master_seed'] ?? 'DotNetBD-Confidential-Hardware-Root-Sealing-Seed';

        $this->verifier = new AttestationVerifier($hwSecret);
        $this->sealer   = new CryptographicSealer($masterSeed);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getVerifier(): AttestationVerifier
    {
        return $this->verifier;
    }

    public function getSealer(): CryptographicSealer
    {
        return $this->sealer;
    }

    /**
     * Create or register an isolated hardware enclave instance.
     */
    public function createEnclave(
        string $enclaveId,
        EnclaveType $type = EnclaveType::INTEL_SGX,
        ?PlatformMeasurement $measurement = null,
        int $memoryLimitMb = 128
    ): EnclaveContext {
        $meas = $measurement ?? PlatformMeasurement::createDefault("enclave-{$enclaveId}");
        $ctx  = new EnclaveContext($enclaveId, $type, $meas, $memoryLimitMb);

        $this->enclaves[$enclaveId] = $ctx;
        return $ctx;
    }

    public function getEnclave(string $enclaveId): ?EnclaveContext
    {
        return $this->enclaves[$enclaveId] ?? null;
    }

    /**
     * Generate an attestation quote certifying the state of an enclave.
     */
    public function attest(string $enclaveId, string $userData = ''): AttestationQuote
    {
        $enclave = $this->getEnclave($enclaveId);
        if ($enclave === null) {
            throw new EnclaveException("Enclave '{$enclaveId}' not found. Cannot generate attestation quote.");
        }

        return $this->verifier->generateQuote(
            $enclave->getMeasurement(),
            $userData,
            $enclave->getEnclaveType()
        );
    }

    /**
     * Verify an attestation quote.
     */
    public function verifyQuote(
        AttestationQuote $quote,
        ?array $trustedMrEnclaves = null,
        ?string $expectedUserData = null
    ): AttestationStatus {
        return $this->verifier->verify($quote, null, $trustedMrEnclaves, $expectedUserData);
    }

    /**
     * Cryptographically seal a secret bound to the current enclave's hardware measurement.
     */
    public function seal(string $enclaveId, string $secret): SealedSecret
    {
        $enclave = $this->getEnclave($enclaveId);
        if ($enclave === null) {
            throw new EnclaveException("Enclave '{$enclaveId}' not found. Cannot seal secret.");
        }

        return $this->sealer->seal($secret, $enclave->getMeasurement());
    }

    /**
     * Unseal a secret, verifying that the current enclave measurement matches the sealing measurement.
     */
    public function unseal(string $enclaveId, SealedSecret $sealed): string
    {
        $enclave = $this->getEnclave($enclaveId);
        if ($enclave === null) {
            throw new EnclaveException("Enclave '{$enclaveId}' not found. Cannot unseal secret.");
        }

        return $this->sealer->unseal($sealed, $enclave->getMeasurement());
    }

    /**
     * @return array<string, EnclaveContext>
     */
    public function getActiveEnclaves(): array
    {
        return $this->enclaves;
    }
}
