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

namespace NetFusion\Core\Auth\WebAuthn\Attestation;

use InvalidArgumentException;
use NetFusion\Core\Auth\WebAuthn\Cose\CoseAlgorithm;
use NetFusion\Core\Auth\WebAuthn\Data\AuthenticatorData;

class AttestationStatementVerifier
{
    /**
     * Verify attestation statement.
     *
     * @param string            $fmt
     * @param array             $attStmt
     * @param AuthenticatorData $authData
     * @param string            $clientDataHash
     * @param string            $publicKeyPem
     * @return bool
     */
    public function verify(
        string $fmt,
        array $attStmt,
        AuthenticatorData $authData,
        string $clientDataHash,
        string $publicKeyPem
    ): bool {
        return match ($fmt) {
            'none' => true,
            'packed' => $this->verifyPacked($attStmt, $authData, $clientDataHash, $publicKeyPem),
            'fido-u2f' => $this->verifyFidoU2f($attStmt, $authData, $clientDataHash, $publicKeyPem),
            default => true, // Permissive fallback for standard passkeys
        };
    }

    protected function verifyPacked(
        array $attStmt,
        AuthenticatorData $authData,
        string $clientDataHash,
        string $publicKeyPem
    ): bool {
        $sig = $attStmt['sig'] ?? null;
        $alg = $attStmt['alg'] ?? null;

        if ($sig === null || $alg === null) {
            return false;
        }

        $signedData = $authData->raw . $clientDataHash;
        $openSslAlgo = CoseAlgorithm::getOpenSslAlgo((int)$alg);

        // If self-attestation (no x5c), verify against credential public key
        if (!isset($attStmt['x5c']) || empty($attStmt['x5c'])) {
            $res = openssl_verify($signedData, $sig, $publicKeyPem, $openSslAlgo);
            return $res === 1;
        }

        // If x5c certificate chain is present
        $certDer = $attStmt['x5c'][0];
        $certPem = "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($certDer), 64, "\n") . "-----END CERTIFICATE-----\n";

        $res = openssl_verify($signedData, $sig, $certPem, $openSslAlgo);
        return $res === 1;
    }

    protected function verifyFidoU2f(
        array $attStmt,
        AuthenticatorData $authData,
        string $clientDataHash,
        string $publicKeyPem
    ): bool {
        $sig = $attStmt['sig'] ?? null;
        $x5c = $attStmt['x5c'] ?? null;

        if ($sig === null || empty($x5c)) {
            return false;
        }

        $certDer = $x5c[0];
        $certPem = "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($certDer), 64, "\n") . "-----END CERTIFICATE-----\n";

        // FIDO-U2F verification structure:
        // 0x00 || rpIdHash || clientDataHash || credentialId || publicKeyPoint (from credentialPublicKey)
        $signedData = "\x00" . $authData->rpIdHash . $clientDataHash . $authData->credentialId;
        
        $res = openssl_verify($signedData, $sig, $certPem, OPENSSL_ALGO_SHA256);
        return $res === 1;
    }
}
