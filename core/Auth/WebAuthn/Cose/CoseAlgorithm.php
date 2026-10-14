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

final class CoseAlgorithm
{
    public const ES256 = -7;   // ECDSA w/ SHA-256 (P-256)
    public const ES384 = -35;  // ECDSA w/ SHA-384 (P-384)
    public const ES512 = -36;  // ECDSA w/ SHA-512 (P-521)
    public const EdDSA = -8;   // Ed25519
    public const PS256 = -37;  // RSASSA-PSS w/ SHA-256
    public const PS384 = -38;  // RSASSA-PSS w/ SHA-384
    public const PS512 = -39;  // RSASSA-PSS w/ SHA-512
    public const RS256 = -257; // RSASSA-PKCS1-v1_5 w/ SHA-256
    public const RS384 = -258; // RSASSA-PKCS1-v1_5 w/ SHA-384
    public const RS512 = -259; // RSASSA-PKCS1-v1_5 w/ SHA-512

    public const KEY_TYPE_OKP = 1;
    public const KEY_TYPE_EC2 = 2;
    public const KEY_TYPE_RSA = 3;

    public const EC2_CURVE_P256 = 1;
    public const EC2_CURVE_P384 = 2;
    public const EC2_CURVE_P521 = 3;

    public static function getName(int $algorithmId): string
    {
        return match ($algorithmId) {
            self::ES256 => 'ES256',
            self::ES384 => 'ES384',
            self::ES512 => 'ES512',
            self::EdDSA => 'EdDSA',
            self::PS256 => 'PS256',
            self::PS384 => 'PS384',
            self::PS512 => 'PS512',
            self::RS256 => 'RS256',
            self::RS384 => 'RS384',
            self::RS512 => 'RS512',
            default => "COSE_ALG_{$algorithmId}",
        };
    }

    public static function getOpenSslAlgo(int $algorithmId): int|string
    {
        return match ($algorithmId) {
            self::ES256, self::RS256, self::PS256 => OPENSSL_ALGO_SHA256,
            self::ES384, self::RS384, self::PS384 => OPENSSL_ALGO_SHA384,
            self::ES512, self::RS512, self::PS512 => OPENSSL_ALGO_SHA512,
            default => OPENSSL_ALGO_SHA256,
        };
    }
}
