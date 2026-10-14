<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Homomorphic/HomomorphicManager.php
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

namespace NetFusion\Core\Security\Homomorphic;

use NetFusion\Core\Security\Homomorphic\Engine\EncryptedComputeEngine;
use NetFusion\Core\Security\Homomorphic\Schemes\BfvEngine;
use NetFusion\Core\Security\Homomorphic\Schemes\Ciphertext;
use NetFusion\Core\Security\Homomorphic\Schemes\CkksEngine;
use NetFusion\Core\Security\Homomorphic\Schemes\KeyPair;
use NetFusion\Core\Security\Homomorphic\Schemes\Plaintext;
use NetFusion\Core\Security\Homomorphic\Smpc\ShamirSecretSharing;

/**
 * Enterprise Homomorphic Encryption & SMPC Master Facade.
 */
class HomomorphicManager
{
    private static ?self $instance = null;

    private BfvEngine $bfv;
    private CkksEngine $ckks;
    private ShamirSecretSharing $shamir;
    private EncryptedComputeEngine $computeEngine;
    private array $config;

    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'bfv_degree'         => 16,
            'bfv_modulus'        => 65537,
            'bfv_plain_modulus'  => 17,
            'ckks_degree'        => 16,
            'ckks_modulus'       => 1048576,
            'ckks_scale'         => 1024.0,
            'smpc_prime'         => ShamirSecretSharing::DEFAULT_PRIME,
        ], $config);

        $this->bfv = new BfvEngine(
            degree: (int)$this->config['bfv_degree'],
            modulus: (int)$this->config['bfv_modulus'],
            plainModulus: (int)$this->config['bfv_plain_modulus']
        );

        $this->ckks = new CkksEngine(
            degree: (int)$this->config['ckks_degree'],
            modulus: (int)$this->config['ckks_modulus'],
            scale: (float)$this->config['ckks_scale']
        );

        $this->shamir = new ShamirSecretSharing(
            prime: (int)$this->config['smpc_prime']
        );

        $this->computeEngine = new EncryptedComputeEngine($this->bfv, $this->ckks);
    }

    public function bfv(): BfvEngine
    {
        return $this->bfv;
    }

    public function ckks(): CkksEngine
    {
        return $this->ckks;
    }

    public function shamir(): ShamirSecretSharing
    {
        return $this->shamir;
    }

    public function compute(): EncryptedComputeEngine
    {
        return $this->computeEngine;
    }

    /**
     * Generate BFV KeyPair.
     */
    public function generateBfvKeys(): KeyPair
    {
        return $this->bfv->generateKeys();
    }

    /**
     * Generate CKKS KeyPair.
     */
    public function generateCkksKeys(): KeyPair
    {
        return $this->ckks->generateKeys();
    }

    /**
     * Encrypt an integer value using BFV scheme.
     */
    public function encryptInt(int $value, KeyPair $keyPair): Ciphertext
    {
        $plain = $this->bfv->encode($value);
        return $this->bfv->encrypt($plain, $keyPair);
    }

    /**
     * Decrypt an integer ciphertext using BFV scheme.
     */
    public function decryptInt(Ciphertext $cipher, KeyPair $keyPair): int
    {
        $plain = $this->bfv->decrypt($cipher, $keyPair);
        return (int)$this->bfv->decode($plain);
    }

    /**
     * Split a secret integer into n shares with threshold k.
     */
    public function splitSecret(int $secret, int $threshold, int $totalShares): array
    {
        return $this->shamir->split($secret, $threshold, $totalShares);
    }

    /**
     * Reconstruct a secret integer from shares.
     */
    public function reconstructSecret(array $shares, int $threshold): int
    {
        return $this->shamir->reconstruct($shares, $threshold);
    }
}
