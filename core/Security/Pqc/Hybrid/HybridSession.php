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

namespace NetFusion\Core\Security\Pqc\Hybrid;

use RuntimeException;

/**
 * Class HybridSession
 *
 * Represents an active post-quantum secure session with symmetric master key.
 */
class HybridSession
{
    protected int $sendSequence = 0;
    protected int $receiveSequence = 0;

    public function __construct(
        protected readonly string $masterKey,
        protected readonly string $sessionId = '',
        protected readonly string $cipher = 'aes-256-gcm'
    ) {
    }

    public function getMasterKey(): string
    {
        return $this->masterKey;
    }

    public function getMasterKeyHex(): string
    {
        return bin2hex($this->masterKey);
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Encrypt a message using the session key and AEAD (AES-256-GCM).
     *
     * @param string $plaintext
     * @param string $aad Optional Associated Authenticated Data
     * @return array{ciphertext: string, iv: string, tag: string, base64: string}
     */
    public function encrypt(string $plaintext, string $aad = ''): array
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            $this->cipher,
            $this->masterKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException("Session encryption failed: " . openssl_error_string());
        }

        $payload = pack('C', strlen($iv)) . $iv . pack('C', strlen($tag)) . $tag . $ciphertext;

        return [
            'ciphertext' => $ciphertext,
            'iv'         => $iv,
            'tag'        => $tag,
            'base64'     => base64_encode($payload),
        ];
    }

    /**
     * Decrypt a message using the session key.
     *
     * @param string $payload Either raw binary payload or base64 string
     * @param string $aad Optional Associated Authenticated Data
     * @return string
     */
    public function decrypt(string $payload, string $aad = ''): string
    {
        $raw = base64_decode($payload, true);
        if ($raw !== false && strlen($raw) > 30) {
            $payload = $raw;
        }

        $ivLen = ord($payload[0]);
        $iv = substr($payload, 1, $ivLen);
        $tagLen = ord($payload[1 + $ivLen]);
        $tag = substr($payload, 2 + $ivLen, $tagLen);
        $ciphertext = substr($payload, 2 + $ivLen + $tagLen);

        $plaintext = openssl_decrypt(
            $ciphertext,
            $this->cipher,
            $this->masterKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad
        );

        if ($plaintext === false) {
            throw new RuntimeException("Session decryption failed: authentication tag mismatch or corrupted data");
        }

        return $plaintext;
    }
}
