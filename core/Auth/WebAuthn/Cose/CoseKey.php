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

namespace NetFusion\Core\Auth\WebAuthn\Cose;

use InvalidArgumentException;
use NetFusion\Core\Auth\WebAuthn\Cbor\CborDecoder;

class CoseKey
{
    /**
     * Parse raw CBOR binary or decoded COSE map and convert to PEM format.
     *
     * @param string|array $coseData
     * @param int|null     $bytesConsumed
     * @return array{pem: string, algorithm: int, kty: int}
     */
    public static function parse(string|array $coseData, ?int &$bytesConsumed = null): array
    {
        $offset = $bytesConsumed ?? 0;
        $map = is_array($coseData) ? $coseData : CborDecoder::decode($coseData, $offset);
        $bytesConsumed = $offset;
        if (!is_array($map)) {
            throw new InvalidArgumentException('Invalid COSE Key: decoded structure is not a map.');
        }

        $kty = (int)($map[1] ?? 0); // 1 = OKP, 2 = EC2, 3 = RSA
        $alg = (int)($map[3] ?? CoseAlgorithm::ES256);

        $pem = match ($kty) {
            CoseAlgorithm::KEY_TYPE_EC2 => self::buildEc2Pem($map),
            CoseAlgorithm::KEY_TYPE_RSA => self::buildRsaPem($map),
            default => throw new InvalidArgumentException("Unsupported COSE key type (kty: {$kty})."),
        };

        return [
            'pem' => $pem,
            'algorithm' => $alg,
            'kty' => $kty,
        ];
    }

    /**
     * Build standard PEM for EC2 (P-256) public key.
     */
    protected static function buildEc2Pem(array $map): string
    {
        $crv = (int)($map[-1] ?? CoseAlgorithm::EC2_CURVE_P256);
        $x = (string)($map[-2] ?? '');
        $y = (string)($map[-3] ?? '');

        if ($crv !== CoseAlgorithm::EC2_CURVE_P256) {
            throw new InvalidArgumentException("Unsupported EC curve ({$crv}). Only P-256 is supported.");
        }

        if (strlen($x) < 32) {
            $x = str_pad($x, 32, "\0", STR_PAD_LEFT);
        } elseif (strlen($x) > 32) {
            $x = substr($x, -32);
        }

        if (strlen($y) < 32) {
            $y = str_pad($y, 32, "\0", STR_PAD_LEFT);
        } elseif (strlen($y) > 32) {
            $y = substr($y, -32);
        }

        if (strlen($x) !== 32 || strlen($y) !== 32) {
            throw new InvalidArgumentException('Invalid EC coordinates length for P-256.');
        }

        // Uncompressed EC point: 0x04 || X || Y (65 bytes)
        $point = "\x04" . $x . $y;

        // AlgorithmIdentifier for id-ecPublicKey (1.2.840.10045.2.1) + prime256v1 (1.2.840.10045.3.1.7)
        // 30 13 06 07 2a 86 48 ce 3d 02 01 06 08 2a 86 48 ce 3d 03 01 07
        $algId = "\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";

        // Bit String: 0x00 (padding/unused bits) || point
        $bitString = self::derBitString("\x00" . $point);

        // SubjectPublicKeyInfo = SEQUENCE(algId, bitString)
        $spki = self::derSequence($algId . $bitString);

        return self::toPem($spki, 'PUBLIC KEY');
    }

    /**
     * Build standard PEM for RSA public key.
     */
    protected static function buildRsaPem(array $map): string
    {
        $n = (string)($map[-1] ?? ''); // modulus
        $e = (string)($map[-2] ?? ''); // exponent

        if (empty($n) || empty($e)) {
            throw new InvalidArgumentException('RSA modulus or exponent missing in COSE map.');
        }

        $derN = self::derInteger($n);
        $derE = self::derInteger($e);
        $rsaPubKey = self::derSequence($derN . $derE);

        // AlgorithmIdentifier for rsaEncryption (1.2.840.113549.1.1.1) with NULL parameter
        // 30 0d 06 09 2a 86 48 86 f7 0d 01 01 01 05 00
        $algId = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";

        $bitString = self::derBitString("\x00" . $rsaPubKey);
        $spki = self::derSequence($algId . $bitString);

        return self::toPem($spki, 'PUBLIC KEY');
    }

    protected static function derSequence(string $contents): string
    {
        return "\x30" . self::derLength(strlen($contents)) . $contents;
    }

    protected static function derBitString(string $contents): string
    {
        return "\x03" . self::derLength(strlen($contents)) . $contents;
    }

    protected static function derInteger(string $bytes): string
    {
        // Positive ASN.1 INTEGER: prepend 0x00 if leading bit is set
        if (ord($bytes[0]) > 0x7f) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . self::derLength(strlen($bytes)) . $bytes;
    }

    protected static function derLength(int $len): string
    {
        if ($len < 128) {
            return chr($len);
        }

        $bytes = '';
        while ($len > 0) {
            $bytes = chr($len & 0xff) . $bytes;
            $len >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    protected static function toPem(string $der, string $type = 'PUBLIC KEY'): string
    {
        $b64 = chunk_split(base64_encode($der), 64, "\n");
        return "-----BEGIN {$type}-----\n" . $b64 . "-----END {$type}-----\n";
    }
}
