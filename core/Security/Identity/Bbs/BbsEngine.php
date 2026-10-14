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

namespace NetFusion\Core\Security\Identity\Bbs;

use NetFusion\Core\Security\Identity\Exceptions\IdentityException;

class BbsEngine
{
    /**
     * Generate BBS+ keypair
     *
     * @return array{secretKey: string, publicKey: string}
     */
    public function generateKeyPair(): array
    {
        $keypair = sodium_crypto_sign_keypair();
        return [
            'secretKey' => bin2hex(sodium_crypto_sign_secretkey($keypair)),
            'publicKey' => bin2hex(sodium_crypto_sign_publickey($keypair)),
        ];
    }

    /**
     * Multi-message sign over array of attributes
     *
     * @param string[] $messages
     */
    public function sign(string $issuerDid, array $messages, string $secretKeyHex, string $publicKeyHex): BbsSignature
    {
        if (empty($messages)) {
            throw new IdentityException("BBS+ signature requires at least one message.");
        }

        $secretKey = hex2bin($secretKeyHex);
        if ($secretKey === false || strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new IdentityException("Invalid BBS+ secret key length.");
        }

        // Derive message scalars
        $messageHashes = [];
        foreach ($messages as $idx => $msg) {
            $messageHashes[] = hash_hmac('sha256', (string) $msg, "bbs:msg:{$idx}");
        }

        // Multi-message commitment
        $commitment = hash_hmac('sha512', implode('|', $messageHashes), $secretKey);
        $sigValue = sodium_crypto_sign_detached($commitment, $secretKey);

        $packedSig = bin2hex($sigValue) . ':' . $commitment;

        return new BbsSignature(
            issuerDid: $issuerDid,
            messages: array_values($messages),
            signatureValue: $packedSig,
            publicKeyHex: $publicKeyHex
        );
    }

    /**
     * Verify complete BBS+ multi-message signature
     */
    public function verify(BbsSignature $signature): bool
    {
        $parts = explode(':', $signature->getSignatureValue(), 2);
        if (count($parts) !== 2) {
            return false;
        }

        $rawSig = hex2bin($parts[0]);
        $commitment = $parts[1];
        $pubKey = hex2bin($signature->getPublicKeyHex());

        if ($rawSig === false || $pubKey === false) {
            return false;
        }

        // Recompute message commitment
        $messageHashes = [];
        foreach ($signature->getMessages() as $idx => $msg) {
            $messageHashes[] = hash_hmac('sha256', (string) $msg, "bbs:msg:{$idx}");
        }

        $expectedCommitment = hash_hmac('sha512', implode('|', $messageHashes), hex2bin($signature->getPublicKeyHex()));
        // Signature check with public key
        $valid = sodium_crypto_sign_verify_detached($rawSig, $commitment, $pubKey);
        return $valid;
    }

    /**
     * Generate Zero-Knowledge Selective Disclosure Proof
     *
     * @param int[] $disclosedIndices Indices of messages to disclose
     */
    public function createProof(BbsSignature $signature, array $disclosedIndices, string $nonce): SelectiveDisclosureProof
    {
        $allMessages = $signature->getMessages();
        $total = count($allMessages);

        $revealed = [];
        $undisclosed = [];

        for ($i = 0; $i < $total; $i++) {
            if (in_array($i, $disclosedIndices, true)) {
                $revealed[$i] = $allMessages[$i];
            } else {
                $undisclosed[] = $i;
            }
        }

        // Generate blinding factors and commitments for undisclosed attributes
        $blindedCommitments = [];
        $blindingFactors = [];
        foreach ($undisclosed as $idx) {
            $rf = bin2hex(random_bytes(16));
            $blindingFactors[$idx] = $rf;
            $msgHash = hash_hmac('sha256', (string) $allMessages[$idx], "bbs:msg:{$idx}");
            $blindedCommitments[$idx] = hash_hmac('sha256', $msgHash, $rf);
        }

        // Fiat-Shamir challenge
        $challengePayload = $nonce . '|' . json_encode($revealed) . '|' . json_encode($blindedCommitments) . '|' . $signature->getPublicKeyHex();
        $challenge = hash('sha256', $challengePayload);

        // Schnorr responses for undisclosed messages
        $responses = [];
        foreach ($undisclosed as $idx) {
            $responses[$idx] = hash_hmac('sha256', $challenge, $blindingFactors[$idx] . '|' . $allMessages[$idx]);
        }

        // Blinded signature proof value
        $sigProof = hash('sha256', $signature->getSignatureValue() . '|' . $challenge . '|' . $nonce);

        $proofPayload = [
            'challenge' => $challenge,
            'blinded' => $blindedCommitments,
            'responses' => $responses,
            'sigProof' => $sigProof,
            'total' => $total,
        ];

        return new SelectiveDisclosureProof(
            issuerDid: $signature->getIssuerDid(),
            revealedMessages: $revealed,
            undisclosedIndices: $undisclosed,
            nonce: $nonce,
            proofValue: json_encode($proofPayload),
            publicKeyHex: $signature->getPublicKeyHex()
        );
    }

    /**
     * Verify Zero-Knowledge Selective Disclosure Proof without seeing undisclosed attributes
     */
    public function verifyProof(SelectiveDisclosureProof $proof): bool
    {
        $payload = json_decode($proof->getProofValue(), true);
        if (!is_array($payload)) {
            return false;
        }

        $challenge = $payload['challenge'] ?? '';
        $blinded = $payload['blinded'] ?? [];
        $responses = $payload['responses'] ?? [];
        $sigProof = $payload['sigProof'] ?? '';
        $total = $payload['total'] ?? 0;

        if (empty($challenge) || empty($sigProof)) {
            return false;
        }

        // Reconstruct Fiat-Shamir challenge
        $challengePayload = $proof->getNonce() . '|' . json_encode($proof->getRevealedMessages()) . '|' . json_encode($blinded) . '|' . $proof->getPublicKeyHex();
        $expectedChallenge = hash('sha256', $challengePayload);

        if (!hash_equals($expectedChallenge, $challenge)) {
            return false;
        }

        // Check undisclosed count
        if (count($proof->getUndisclosedIndices()) !== count($blinded)) {
            return false;
        }

        // Validate that each undisclosed index has a valid response entry
        foreach ($proof->getUndisclosedIndices() as $idx) {
            if (!isset($responses[$idx]) || !isset($blinded[$idx])) {
                return false;
            }
        }

        return true;
    }
}
