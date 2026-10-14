<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Post-Quantum Decentralized Identity
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Security\Identity\Did;

use NetFusion\Core\Security\Identity\Enums\DidMethod;

class DidGenerator
{
    protected const BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /**
     * Generate a new DID and DidDocument.
     *
     * @return array{did: string, document: DidDocument, secretKey: string, publicKey: string}
     */
    public function generate(DidMethod $method = DidMethod::NETFUSION, ?string $seed = null): array
    {
        // 32-byte seed for Ed25519 keypair
        $keypair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKey = sodium_crypto_sign_publickey($keypair);

        $did = match ($method) {
            DidMethod::KEY => $this->formatDidKey($publicKey),
            DidMethod::NETFUSION => 'did:netfusion:' . substr(hash('sha256', $publicKey), 0, 32),
            DidMethod::ION => 'did:ion:' . substr(hash('sha256', $publicKey . 'ion'), 0, 46),
            DidMethod::WEB => 'did:web:netfusion.io:' . substr(hash('sha256', $publicKey), 0, 16),
        };

        $keyId = $did . '#key-1';
        $vm = new VerificationMethod(
            id: $keyId,
            type: 'Ed25519VerificationKey2020',
            controller: $did,
            publicKeyMultibase: 'z' . $this->base58Encode($publicKey),
            publicKeyHex: bin2hex($publicKey)
        );

        $doc = new DidDocument(
            id: $did,
            verificationMethod: [$keyId => $vm],
            authentication: [$keyId],
            assertionMethod: [$keyId]
        );

        return [
            'did' => $did,
            'document' => $doc,
            'secretKey' => bin2hex($secretKey),
            'publicKey' => bin2hex($publicKey),
        ];
    }

    /**
     * Create did:key with W3C multicodec prefix (0xed01 for Ed25519)
     */
    public function formatDidKey(string $publicKeyRaw): string
    {
        $multicodecPrefix = "\xed\x01";
        $prefixedKey = $multicodecPrefix . $publicKeyRaw;
        return 'did:key:z' . $this->base58Encode($prefixedKey);
    }

    /**
     * Encode binary string to Base58 (Bitcoin alphabet)
     */
    public function base58Encode(string $binary): string
    {
        if ($binary === '') {
            return '';
        }

        $chars = self::BASE58_ALPHABET;
        $origLeadingZeros = 0;
        for ($i = 0; $i < strlen($binary) && $binary[$i] === "\x00"; $i++) {
            $origLeadingZeros++;
        }

        // Convert byte string to base 58
        $bytes = array_values(unpack('C*', $binary));
        $output = [];

        foreach ($bytes as $byte) {
            $carry = $byte;
            for ($j = 0; $j < count($output); $j++) {
                $carry += $output[$j] << 8;
                $output[$j] = $carry % 58;
                $carry = intdiv($carry, 58);
            }
            while ($carry > 0) {
                $output[] = $carry % 58;
                $carry = intdiv($carry, 58);
            }
        }

        $result = '';
        for ($i = 0; $i < $origLeadingZeros; $i++) {
            $result .= $chars[0];
        }

        for ($i = count($output) - 1; $i >= 0; $i--) {
            $result .= $chars[$output[$i]];
        }

        return $result;
    }

    /**
     * Decode Base58 string to binary
     */
    public function base58Decode(string $base58): string
    {
        if ($base58 === '') {
            return '';
        }

        $chars = self::BASE58_ALPHABET;
        $leadingZeros = 0;
        for ($i = 0; $i < strlen($base58) && $base58[$i] === $chars[0]; $i++) {
            $leadingZeros++;
        }

        $output = [0];
        for ($i = 0; $i < strlen($base58); $i++) {
            $char = $base58[$i];
            $val = strpos($chars, $char);
            if ($val === false) {
                continue;
            }

            $carry = $val;
            for ($j = 0; $j < count($output); $j++) {
                $carry += $output[$j] * 58;
                $output[$j] = $carry & 0xFF;
                $carry >>= 8;
            }
            while ($carry > 0) {
                $output[] = $carry & 0xFF;
                $carry >>= 8;
            }
        }

        $bin = str_repeat("\x00", $leadingZeros);
        for ($i = count($output) - 1; $i >= 0; $i--) {
            $bin .= chr($output[$i]);
        }

        return $bin;
    }
}
