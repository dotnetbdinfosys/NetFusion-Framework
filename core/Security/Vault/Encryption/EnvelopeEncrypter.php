<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Security\Vault\Encryption;

use InvalidArgumentException;
use RuntimeException;
use NetFusion\Core\Security\Vault\Encryption\Contracts\KeyProviderInterface;

class EnvelopeEncrypter
{
    public const CIPHER = 'aes-256-gcm';

    public function __construct(
        protected KeyProviderInterface $keyProvider
    ) {
    }

    public function getKeyProvider(): KeyProviderInterface
    {
        return $this->keyProvider;
    }

    /**
     * Envelope-encrypt plaintext using an ephemeral DEK wrapped with active KEK.
     */
    public function encrypt(string $plaintext, ?int $kekVersion = null): EncryptedPayload
    {
        $version = $kekVersion ?? $this->keyProvider->getActiveVersion();
        $kek = $this->keyProvider->getMasterKey($version);

        // 1. Generate Ephemeral 256-bit Data Encryption Key (DEK)
        $dek = random_bytes(32);

        // 2. Encrypt Plaintext with DEK using AES-256-GCM
        $iv = random_bytes(12); // 96-bit standard GCM IV
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $dek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Failed to encrypt payload with data encryption key.');
        }

        // 3. Encrypt DEK with Master Key (KEK)
        $edekIv = random_bytes(12);
        $edekTag = '';
        $edek = openssl_encrypt(
            $dek,
            self::CIPHER,
            $kek,
            OPENSSL_RAW_DATA,
            $edekIv,
            $edekTag,
            '',
            16
        );

        if ($edek === false) {
            throw new RuntimeException('Failed to wrap data encryption key with master key.');
        }

        return new EncryptedPayload(
            version: $version,
            cipher: self::CIPHER,
            iv: base64_encode($iv),
            tag: base64_encode($tag),
            edek: base64_encode($edek),
            edekIv: base64_encode($edekIv),
            edekTag: base64_encode($edekTag),
            ciphertext: base64_encode($ciphertext),
            timestamp: microtime(true)
        );
    }

    /**
     * Decrypt an envelope-encrypted payload.
     */
    public function decrypt(EncryptedPayload|array|string $payload): string
    {
        if (is_string($payload)) {
            $payload = EncryptedPayload::fromJson($payload);
        } elseif (is_array($payload)) {
            $payload = EncryptedPayload::fromArray($payload);
        }

        $kek = $this->keyProvider->getMasterKey($payload->version);

        // 1. Decrypt DEK using Master Key
        $edekBin = base64_decode($payload->edek);
        $edekIvBin = base64_decode($payload->edekIv);
        $edekTagBin = base64_decode($payload->edekTag);

        $dek = openssl_decrypt(
            $edekBin,
            self::CIPHER,
            $kek,
            OPENSSL_RAW_DATA,
            $edekIvBin,
            $edekTagBin
        );

        if ($dek === false) {
            throw new RuntimeException("Failed to unwrap DEK. Master key version {$payload->version} mismatch or corrupted tag.");
        }

        // 2. Decrypt Ciphertext using unwrapped DEK
        $cipherBin = base64_decode($payload->ciphertext);
        $ivBin = base64_decode($payload->iv);
        $tagBin = base64_decode($payload->tag);

        $plaintext = openssl_decrypt(
            $cipherBin,
            $payload->cipher,
            $dek,
            OPENSSL_RAW_DATA,
            $ivBin,
            $tagBin
        );

        if ($plaintext === false) {
            throw new RuntimeException('Failed to decrypt ciphertext with unwrapped DEK (authentication tag verification failed).');
        }

        return $plaintext;
    }

    /**
     * Re-encrypt an envelope payload with a new KEK version by re-wrapping its DEK without touching data ciphertext.
     */
    public function rewrap(EncryptedPayload $payload, ?int $newKekVersion = null): EncryptedPayload
    {
        $newVersion = $newKekVersion ?? $this->keyProvider->getActiveVersion();
        if ($payload->version === $newVersion) {
            return $payload;
        }

        $oldKek = $this->keyProvider->getMasterKey($payload->version);
        $newKek = $this->keyProvider->getMasterKey($newVersion);

        // Unwrap DEK using old KEK
        $edekBin = base64_decode($payload->edek);
        $edekIvBin = base64_decode($payload->edekIv);
        $edekTagBin = base64_decode($payload->edekTag);

        $dek = openssl_decrypt(
            $edekBin,
            self::CIPHER,
            $oldKek,
            OPENSSL_RAW_DATA,
            $edekIvBin,
            $edekTagBin
        );

        if ($dek === false) {
            throw new RuntimeException("Failed to unwrap DEK during rewrap for version {$payload->version}.");
        }

        // Re-wrap DEK using new KEK
        $newEdekIv = random_bytes(12);
        $newEdekTag = '';
        $newEdek = openssl_encrypt(
            $dek,
            self::CIPHER,
            $newKek,
            OPENSSL_RAW_DATA,
            $newEdekIv,
            $newEdekTag,
            '',
            16
        );

        if ($newEdek === false) {
            throw new RuntimeException('Failed to wrap DEK with new master key.');
        }

        return new EncryptedPayload(
            version: $newVersion,
            cipher: $payload->cipher,
            iv: $payload->iv,
            tag: $payload->tag,
            edek: base64_encode($newEdek),
            edekIv: base64_encode($newEdekIv),
            edekTag: base64_encode($newEdekTag),
            ciphertext: $payload->ciphertext,
            timestamp: microtime(true)
        );
    }
}
