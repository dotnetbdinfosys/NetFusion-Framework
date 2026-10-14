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

namespace NetFusion\Core\Auth\WebAuthn\Assertion;

use InvalidArgumentException;
use RuntimeException;
use NetFusion\Core\Auth\WebAuthn\Cose\CoseAlgorithm;
use NetFusion\Core\Auth\WebAuthn\Data\AuthenticatorData;
use NetFusion\Core\Auth\WebAuthn\Data\ClientData;
use NetFusion\Core\Auth\WebAuthn\Data\WebAuthnCredential;

class AssertionVerifier
{
    /**
     * Verify WebAuthn Assertion.
     *
     * @param AuthenticatorData  $authData
     * @param ClientData         $clientData
     * @param string             $signature Binary signature
     * @param WebAuthnCredential $credential
     * @param string             $expectedRpId
     * @param bool               $requireUserVerification
     * @return bool
     */
    public function verify(
        AuthenticatorData $authData,
        ClientData $clientData,
        string $signature,
        WebAuthnCredential $credential,
        string $expectedRpId,
        bool $requireUserVerification = false
    ): bool {
        // 1. Verify ClientData Type
        if ($clientData->type !== 'webauthn.get') {
            throw new InvalidArgumentException("Invalid clientData type: expected 'webauthn.get', got '{$clientData->type}'.");
        }

        // 2. Verify RP ID Hash
        $expectedRpIdHash = hash('sha256', $expectedRpId, true);
        if (!hash_equals($expectedRpIdHash, $authData->rpIdHash)) {
            throw new InvalidArgumentException('RP ID hash mismatch in authenticatorData.');
        }

        // 3. Verify User Present (UP) Flag
        if (!$authData->isUserPresent()) {
            throw new InvalidArgumentException('User Present (UP) flag is not set in authenticatorData.');
        }

        // 4. Verify User Verified (UV) Flag if required
        if ($requireUserVerification && !$authData->isUserVerified()) {
            throw new InvalidArgumentException('User Verification (UV) is required but flag was not set.');
        }

        // 5. Anti-replay and Clone Detection via Sign Counter
        if ($authData->signCount > 0 || $credential->signCount > 0) {
            if ($authData->signCount <= $credential->signCount) {
                throw new RuntimeException(
                    "Potential authenticator cloning or replay attack detected! " .
                    "Stored counter: {$credential->signCount}, incoming counter: {$authData->signCount}."
                );
            }
        }

        // 6. Signed Payload = authenticatorData || SHA256(clientDataJSON)
        $clientDataHash = hash('sha256', $clientData->rawJson, true);
        $signedData = $authData->raw . $clientDataHash;

        // 7. Normalize ECDSA signature if in raw R||S 64-byte format
        $sigDer = strlen($signature) === 64 ? $this->rawSignatureToDer($signature) : $signature;

        $algoId = $credential->coseAlgorithm !== null ? (int)$credential->coseAlgorithm : CoseAlgorithm::ES256;
        $openSslAlgo = CoseAlgorithm::getOpenSslAlgo($algoId);

        $result = openssl_verify($signedData, $sigDer, $credential->publicKeyPem, $openSslAlgo);
        if ($result !== 1) {
            throw new InvalidArgumentException('Assertion signature verification failed.');
        }

        return true;
    }

    /**
     * Convert raw 64-byte ECDSA signature (R || S) to ASN.1 DER format.
     */
    protected function rawSignatureToDer(string $rawSig): string
    {
        $r = substr($rawSig, 0, 32);
        $s = substr($rawSig, 32, 32);

        $derR = $this->derInteger($r);
        $derS = $this->derInteger($s);

        return "\x30" . chr(strlen($derR) + strlen($derS)) . $derR . $derS;
    }

    protected function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || ord($bytes[0]) > 0x7f) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . chr(strlen($bytes)) . $bytes;
    }
}
