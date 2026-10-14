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

namespace NetFusion\Core\Auth\WebAuthn;

use InvalidArgumentException;
use NetFusion\Core\Auth\WebAuthn\Assertion\AssertionVerifier;
use NetFusion\Core\Auth\WebAuthn\Attestation\AttestationStatementVerifier;
use NetFusion\Core\Auth\WebAuthn\Cbor\CborDecoder;
use NetFusion\Core\Auth\WebAuthn\Challenge\ChallengeManager;
use NetFusion\Core\Auth\WebAuthn\Contracts\CredentialStoreInterface;
use NetFusion\Core\Auth\WebAuthn\Contracts\WebAuthnManagerInterface;
use NetFusion\Core\Auth\WebAuthn\Cose\CoseAlgorithm;
use NetFusion\Core\Auth\WebAuthn\Cose\CoseKey;
use NetFusion\Core\Auth\WebAuthn\Data\AuthenticatorData;
use NetFusion\Core\Auth\WebAuthn\Data\ClientData;
use NetFusion\Core\Auth\WebAuthn\Data\PublicKeyCredentialCreationOptions;
use NetFusion\Core\Auth\WebAuthn\Data\PublicKeyCredentialRequestOptions;
use NetFusion\Core\Auth\WebAuthn\Data\WebAuthnCredential;
use NetFusion\Core\Auth\WebAuthn\Store\ArrayCredentialStore;

class WebAuthnManager implements WebAuthnManagerInterface
{
    public function __construct(
        protected ?CredentialStoreInterface $store = null,
        protected ?ChallengeManager $challenges = null,
        protected ?AttestationStatementVerifier $attestationVerifier = null,
        protected ?AssertionVerifier $assertionVerifier = null,
        protected array $config = []
    ) {
        $this->store = $store ?? new ArrayCredentialStore();
        $this->challenges = $challenges ?? new ChallengeManager();
        $this->attestationVerifier = $attestationVerifier ?? new AttestationStatementVerifier();
        $this->assertionVerifier = $assertionVerifier ?? new AssertionVerifier();

        $this->config = array_merge([
            'rp_name' => 'NetFusion Framework',
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'timeout' => 60000,
            'challenge_ttl' => 300,
            'user_verification' => 'preferred',
            'attestation' => 'none',
        ], $config);
    }

    public function getStore(): CredentialStoreInterface
    {
        return $this->store;
    }

    public function getChallengeManager(): ChallengeManager
    {
        return $this->challenges;
    }

    public function generateRegisterOptions(
        string|int $userId,
        string $username,
        string $displayName,
        array $options = []
    ): PublicKeyCredentialCreationOptions {
        $ttl = (int)($options['challenge_ttl'] ?? $this->config['challenge_ttl']);
        $challenge = $this->challenges->generate('register', $userId, $ttl);

        // Exclude existing credentials for this user
        $existing = $this->store->findByUserId($userId);
        $excludeCredentials = [];
        foreach ($existing as $cred) {
            $excludeCredentials[] = [
                'type' => 'public-key',
                'id' => $cred->id,
                'transports' => $cred->transports,
            ];
        }

        $userHandle = ChallengeManager::base64UrlEncode((string)$userId);

        return new PublicKeyCredentialCreationOptions(
            rp: [
                'name' => (string)($options['rp_name'] ?? $this->config['rp_name']),
                'id' => (string)($options['rp_id'] ?? $this->config['rp_id']),
            ],
            user: [
                'id' => $userHandle,
                'name' => $username,
                'displayName' => $displayName,
            ],
            challenge: $challenge,
            pubKeyCredParams: $options['pubKeyCredParams'] ?? [
                ['type' => 'public-key', 'alg' => CoseAlgorithm::ES256],
                ['type' => 'public-key', 'alg' => CoseAlgorithm::RS256],
                ['type' => 'public-key', 'alg' => CoseAlgorithm::EdDSA],
                ['type' => 'public-key', 'alg' => CoseAlgorithm::PS256],
            ],
            timeout: (int)($options['timeout'] ?? $this->config['timeout']),
            excludeCredentials: $excludeCredentials,
            authenticatorSelection: $options['authenticatorSelection'] ?? [
                'authenticatorAttachment' => 'platform',
                'residentKey' => 'preferred',
                'userVerification' => (string)($options['user_verification'] ?? $this->config['user_verification']),
            ],
            attestation: (string)($options['attestation'] ?? $this->config['attestation'])
        );
    }

    public function verifyRegistration(
        array $response,
        string $expectedChallenge,
        array $metadata = []
    ): WebAuthnCredential {
        $clientDataRaw = $this->extractRawField($response, 'clientDataJSON');
        $clientData = ClientData::fromJson($clientDataRaw);

        // 1. Verify ClientData
        if ($clientData->type !== 'webauthn.create') {
            throw new InvalidArgumentException("Invalid clientData type: expected 'webauthn.create', got '{$clientData->type}'.");
        }

        if (!hash_equals($expectedChallenge, $clientData->challenge)) {
            throw new InvalidArgumentException('Challenge mismatch in registration.');
        }

        $expectedOrigin = (string)($metadata['origin'] ?? $this->config['origin']);
        if (!empty($expectedOrigin) && $clientData->origin !== $expectedOrigin) {
            throw new InvalidArgumentException("Origin mismatch: expected '{$expectedOrigin}', got '{$clientData->origin}'.");
        }

        // 2. Decode AttestationObject (CBOR)
        $attestationObjRaw = $this->extractRawField($response, 'attestationObject');
        $attestationMap = CborDecoder::decode($attestationObjRaw);

        if (!is_array($attestationMap) || !isset($attestationMap['fmt'], $attestationMap['authData'], $attestationMap['attStmt'])) {
            throw new InvalidArgumentException('Invalid attestationObject structure.');
        }

        $fmt = (string)$attestationMap['fmt'];
        $attStmt = (array)$attestationMap['attStmt'];
        $authDataRaw = (string)$attestationMap['authData'];

        // 3. Parse AuthenticatorData
        $authData = AuthenticatorData::parse($authDataRaw);

        $expectedRpId = (string)($metadata['rp_id'] ?? $this->config['rp_id']);
        $expectedRpIdHash = hash('sha256', $expectedRpId, true);
        if (!hash_equals($expectedRpIdHash, $authData->rpIdHash)) {
            throw new InvalidArgumentException('RP ID hash mismatch in registration authData.');
        }

        if (!$authData->isUserPresent()) {
            throw new InvalidArgumentException('User Present (UP) flag not set.');
        }

        if (!$authData->hasAttestedCredentialData() || $authData->credentialId === null) {
            throw new InvalidArgumentException('Attested credential data missing in registration.');
        }

        // 4. Parse COSE Public Key
        $coseParsed = CoseKey::parse($authData->credentialPublicKeyRaw);
        $publicKeyPem = $coseParsed['pem'];
        $coseAlg = $coseParsed['algorithm'];

        // 5. Verify Attestation Statement
        $clientDataHash = hash('sha256', $clientDataRaw, true);
        $verified = $this->attestationVerifier->verify($fmt, $attStmt, $authData, $clientDataHash, $publicKeyPem);
        if (!$verified) {
            throw new InvalidArgumentException("Attestation statement verification failed for format '{$fmt}'.");
        }

        // 6. Build Credential Object
        $credentialIdB64 = ChallengeManager::base64UrlEncode($authData->credentialId);
        $userId = $metadata['user_id'] ?? (isset($response['userHandle']) ? ChallengeManager::base64UrlDecode($response['userHandle']) : 0);

        $credential = new WebAuthnCredential(
            id: $credentialIdB64,
            userId: $userId,
            publicKeyPem: $publicKeyPem,
            attestationType: $fmt,
            aaguid: $authData->aaguid ?? '',
            signCount: $authData->signCount,
            coseAlgorithm: CoseAlgorithm::getName($coseAlg),
            name: $metadata['name'] ?? 'Passkey',
            createdAt: date('Y-m-d H:i:s'),
            transports: $response['transports'] ?? ($response['response']['transports'] ?? [])
        );

        $this->store->save($credential);
        return $credential;
    }

    public function generateLoginOptions(
        string|int|null $userId = null,
        array $options = []
    ): PublicKeyCredentialRequestOptions {
        $ttl = (int)($options['challenge_ttl'] ?? $this->config['challenge_ttl']);
        $challenge = $this->challenges->generate('login', $userId, $ttl);

        $allowCredentials = [];
        if ($userId !== null) {
            $userCreds = $this->store->findByUserId($userId);
            foreach ($userCreds as $cred) {
                $allowCredentials[] = [
                    'type' => 'public-key',
                    'id' => $cred->id,
                    'transports' => $cred->transports,
                ];
            }
        }

        return new PublicKeyCredentialRequestOptions(
            challenge: $challenge,
            timeout: (int)($options['timeout'] ?? $this->config['timeout']),
            rpId: (string)($options['rp_id'] ?? $this->config['rp_id']),
            allowCredentials: $allowCredentials,
            userVerification: (string)($options['user_verification'] ?? $this->config['user_verification']),
            extensions: $options['extensions'] ?? []
        );
    }

    public function verifyAuthentication(
        array $response,
        string $expectedChallenge
    ): WebAuthnCredential {
        $credentialId = (string)($response['id'] ?? ($response['rawId'] ?? ''));
        if (empty($credentialId)) {
            throw new InvalidArgumentException('Missing credential ID in authentication response.');
        }

        $credential = $this->store->findById($credentialId);
        if ($credential === null) {
            throw new InvalidArgumentException("WebAuthn credential not found for ID: {$credentialId}.");
        }

        $clientDataRaw = $this->extractRawField($response, 'clientDataJSON');
        $clientData = ClientData::fromJson($clientDataRaw);

        if (!hash_equals($expectedChallenge, $clientData->challenge)) {
            throw new InvalidArgumentException('Challenge mismatch in authentication.');
        }

        $authDataRaw = $this->extractRawField($response, 'authenticatorData');
        $authData = AuthenticatorData::parse($authDataRaw);

        $sigRaw = $this->extractRawField($response, 'signature');

        $expectedRpId = (string)($this->config['rp_id'] ?? 'localhost');
        $requireUv = ($this->config['user_verification'] ?? 'preferred') === 'required';

        $this->assertionVerifier->verify(
            authData: $authData,
            clientData: $clientData,
            signature: $sigRaw,
            credential: $credential,
            expectedRpId: $expectedRpId,
            requireUserVerification: $requireUv
        );

        // Update signature counter
        $this->store->updateCounter($credential->id, $authData->signCount);
        $credential->signCount = $authData->signCount;

        return $credential;
    }

    protected function extractRawField(array $response, string $field): string
    {
        $val = $response[$field] ?? ($response['response'][$field] ?? null);
        if ($val === null) {
            throw new InvalidArgumentException("Field '{$field}' missing in WebAuthn response.");
        }

        // If base64url or standard base64 encoded
        if (is_string($val) && (preg_match('/^[a-zA-Z0-9_-]+={0,2}$/', $val) || preg_match('/^[a-zA-Z0-9+\/]+={0,2}$/', $val))) {
            $decoded = ChallengeManager::base64UrlDecode($val);
            if ($decoded !== '') {
                return $decoded;
            }
        }

        return (string)$val;
    }
}
