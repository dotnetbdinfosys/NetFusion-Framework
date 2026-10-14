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

namespace NetFusion\Core\Security\Pqc\Vault;

use NetFusion\Core\Security\Pqc\Hybrid\HybridCiphertext;
use NetFusion\Core\Security\Pqc\Hybrid\HybridKeyExchange;
use NetFusion\Core\Security\Pqc\Hybrid\HybridKeyPair;
use RuntimeException;
use InvalidArgumentException;

/**
 * Class PqcEnvelopeVault
 *
 * Quantum-Resistant Envelope Encryption Vault:
 * Protects application secrets, database attributes, and sensitive records using
 * ephemeral symmetric Data Encryption Keys (DEKs) encapsulated with Hybrid PQC (X25519 + ML-KEM-768).
 */
class PqcEnvelopeVault
{
    /**
     * Stored Master Keypairs keyed by key ID.
     *
     * @var array<string, HybridKeyPair>
     */
    protected array $keys = [];

    protected string $defaultKeyId = 'master-v1';
    protected HybridKeyExchange $hybridExchange;

    public function __construct(?HybridKeyExchange $hybridExchange = null)
    {
        $this->hybridExchange = $hybridExchange ?? new HybridKeyExchange();
        // Initialize default master keypair
        $this->keys[$this->defaultKeyId] = $this->hybridExchange->generateKeyPair();
    }

    public function getHybridExchange(): HybridKeyExchange
    {
        return $this->hybridExchange;
    }

    /**
     * Register or rotate a named Master Keypair.
     *
     * @param string $keyId
     * @param HybridKeyPair|null $keyPair If null, a new keypair is generated automatically
     * @return HybridKeyPair
     */
    public function registerKey(string $keyId, ?HybridKeyPair $keyPair = null): HybridKeyPair
    {
        $pair = $keyPair ?? $this->hybridExchange->generateKeyPair();
        $this->keys[$keyId] = $pair;
        return $pair;
    }

    public function getKey(string $keyId): ?HybridKeyPair
    {
        return $this->keys[$keyId] ?? null;
    }

    public function hasKey(string $keyId): bool
    {
        return isset($this->keys[$keyId]);
    }

    public function setDefaultKeyId(string $keyId): self
    {
        if (!isset($this->keys[$keyId])) {
            throw new InvalidArgumentException("Key '{$keyId}' not registered in vault");
        }
        $this->defaultKeyId = $keyId;
        return $this;
    }

    public function getDefaultKeyId(): string
    {
        return $this->defaultKeyId;
    }

    /**
     * Encrypt data with Quantum-Resistant Envelope Encryption.
     *
     * @param string $plaintext
     * @param string|null $keyId Key ID or null for default
     * @param string $aad Optional authenticated data
     * @return string Serialized JSON envelope
     */
    public function encrypt(string $plaintext, ?string $keyId = null, string $aad = ''): string
    {
        $kid = $keyId ?? $this->defaultKeyId;
        $keyPair = $this->keys[$kid] ?? null;

        if ($keyPair === null) {
            throw new RuntimeException("Master key '{$kid}' not found in vault");
        }

        // 1. Generate ephemeral session via Hybrid KEM encapsulation
        $encap = $this->hybridExchange->encapsulate($keyPair);
        $ciphertextPayload = $encap['ciphertext'];
        $session = $encap['session'];

        // 2. Encrypt plaintext using negotiated session key (AES-256-GCM)
        $encrypted = $session->encrypt($plaintext, $aad);

        // 3. Assemble Envelope
        $envelope = [
            'v'       => 1,
            'alg'     => 'HYBRID-X25519-MLKEM768-AES256GCM',
            'kid'     => $kid,
            'kem_ct'  => $ciphertextPayload->toBase64(),
            'iv'      => base64_encode($encrypted['iv']),
            'tag'     => base64_encode($encrypted['tag']),
            'ct'      => base64_encode($encrypted['ciphertext']),
        ];

        return json_encode($envelope, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Decrypt an envelope.
     *
     * @param string $envelopeJson
     * @param HybridKeyPair|null $keyPair Optional override keypair
     * @param string $aad Optional authenticated data
     * @return string
     */
    public function decrypt(string $envelopeJson, ?HybridKeyPair $keyPair = null, string $aad = ''): string
    {
        $data = json_decode($envelopeJson, true);
        if (!is_array($data) || !isset($data['kid'], $data['kem_ct'], $data['ct'], $data['iv'], $data['tag'])) {
            throw new InvalidArgumentException("Malformed PQC envelope payload");
        }

        $kid = $data['kid'];
        $recipientKey = $keyPair ?? ($this->keys[$kid] ?? null);

        if ($recipientKey === null) {
            throw new RuntimeException("Private key for kid '{$kid}' not available in vault");
        }

        // 1. Decapsulate ephemeral session key
        $hybridCt = HybridCiphertext::fromBase64($data['kem_ct']);
        $session = $this->hybridExchange->decapsulate($recipientKey, $hybridCt);

        // 2. Decrypt symmetric payload with AES-256-GCM
        $iv = base64_decode($data['iv'], true);
        $tag = base64_decode($data['tag'], true);
        $ct = base64_decode($data['ct'], true);

        $plaintext = openssl_decrypt(
            $ct,
            'aes-256-gcm',
            $session->getMasterKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad
        );

        if ($plaintext === false) {
            throw new RuntimeException("PQC Vault decryption failed: invalid authentication tag or data corrupted");
        }

        return $plaintext;
    }

    /**
     * Re-encrypt an envelope under a new master key (Key Rotation).
     *
     * @param string $envelopeJson
     * @param string $newKeyId
     * @param string $aad
     * @return string
     */
    public function reEncrypt(string $envelopeJson, string $newKeyId, string $aad = ''): string
    {
        $plaintext = $this->decrypt($envelopeJson, null, $aad);
        return $this->encrypt($plaintext, $newKeyId, $aad);
    }
}
