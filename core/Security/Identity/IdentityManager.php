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

namespace NetFusion\Core\Security\Identity;

use NetFusion\Core\Security\Identity\Bbs\BbsEngine;
use NetFusion\Core\Security\Identity\Bbs\BbsSignature;
use NetFusion\Core\Security\Identity\Bbs\SelectiveDisclosureProof;
use NetFusion\Core\Security\Identity\Credentials\StatusList2021;
use NetFusion\Core\Security\Identity\Credentials\VerifiableCredential;
use NetFusion\Core\Security\Identity\Credentials\VerifiablePresentation;
use NetFusion\Core\Security\Identity\Did\DidDocument;
use NetFusion\Core\Security\Identity\Did\DidGenerator;
use NetFusion\Core\Security\Identity\Did\DidResolver;
use NetFusion\Core\Security\Identity\Enums\DidMethod;
use NetFusion\Core\Security\Identity\Enums\ProofType;
use NetFusion\Core\Security\Identity\Exceptions\CredentialRevokedException;
use NetFusion\Core\Security\Identity\Exceptions\IdentityException;
use NetFusion\Core\Security\Identity\TrustRegistry\TrustRegistryEngine;

class IdentityManager
{
    protected DidGenerator $generator;
    protected DidResolver $resolver;
    protected BbsEngine $bbsEngine;
    protected TrustRegistryEngine $trustRegistry;

    /** @var array<string, StatusList2021> */
    protected array $statusLists = [];

    public function __construct(
        ?DidGenerator $generator = null,
        ?DidResolver $resolver = null,
        ?BbsEngine $bbsEngine = null,
        ?TrustRegistryEngine $trustRegistry = null
    ) {
        $this->generator = $generator ?? new DidGenerator();
        $this->resolver = $resolver ?? new DidResolver($this->generator);
        $this->bbsEngine = $bbsEngine ?? new BbsEngine();
        $this->trustRegistry = $trustRegistry ?? new TrustRegistryEngine();
    }

    public function getGenerator(): DidGenerator
    {
        return $this->generator;
    }

    public function getResolver(): DidResolver
    {
        return $this->resolver;
    }

    public function getBbsEngine(): BbsEngine
    {
        return $this->bbsEngine;
    }

    public function getTrustRegistry(): TrustRegistryEngine
    {
        return $this->trustRegistry;
    }

    /**
     * Create and register a new Decentralized Identifier (DID)
     */
    public function createDid(DidMethod $method = DidMethod::NETFUSION): array
    {
        $res = $this->generator->generate($method);
        $this->resolver->register($res['document']);
        return $res;
    }

    /**
     * Resolve a DID into its DidDocument
     */
    public function resolveDid(string $did): DidDocument
    {
        return $this->resolver->resolve($did);
    }

    /**
     * Create and sign a Verifiable Credential 2.0
     */
    public function issueCredential(
        string $issuerDid,
        array $credentialSubject,
        string $secretKeyHex,
        array $type = ['VerifiableCredential'],
        ?string $expirationDate = null,
        ?array $statusRef = null,
        ProofType $proofType = ProofType::ED25519_2020
    ): VerifiableCredential {
        $doc = $this->resolver->resolve($issuerDid);
        $vm = $doc->findVerificationMethodForAssertion();
        if ($vm === null) {
            throw new IdentityException("No assertionMethod found on issuer DID document for: {$issuerDid}");
        }

        $id = 'urn:uuid:' . bin2hex(random_bytes(16));
        $vc = new VerifiableCredential(
            id: $id,
            issuer: $issuerDid,
            credentialSubject: $credentialSubject,
            type: $type,
            expirationDate: $expirationDate,
            credentialStatus: $statusRef
        );

        $digest = $vc->getCanonicalDigest();
        $secretKey = hex2bin($secretKeyHex);
        if ($secretKey === false) {
            throw new IdentityException("Invalid issuer secret key format.");
        }

        $signature = sodium_crypto_sign_detached($digest, $secretKey);

        $proof = [
            'type' => $proofType->value,
            'created' => gmdate('Y-m-d\TH:i:s\Z'),
            'verificationMethod' => $vm->getId(),
            'proofPurpose' => 'assertionMethod',
            'proofValue' => bin2hex($signature),
        ];

        $vc->setProof($proof);
        return $vc;
    }

    /**
     * Verify cryptographic authenticity, expiry and revocation status of a Verifiable Credential
     *
     * @return array{valid: bool, reason: ?string}
     */
    public function verifyCredential(VerifiableCredential $vc, ?string $ecosystemId = null): array
    {
        if ($vc->isExpired()) {
            return ['valid' => false, 'reason' => 'Credential expired.'];
        }

        // Verify status list revocation
        $status = $vc->getCredentialStatus();
        if ($status !== null && isset($status['id'], $status['statusListIndex'])) {
            $listId = (string) $status['id'];
            $index = (int) $status['statusListIndex'];
            if (isset($this->statusLists[$listId]) && $this->statusLists[$listId]->isRevoked($index)) {
                return ['valid' => false, 'reason' => 'Credential has been revoked in StatusList2021.'];
            }
        }

        // Verify Trust Registry authorization if required
        if ($ecosystemId !== null) {
            $types = $vc->getType();
            $schema = end($types);
            if (!$this->trustRegistry->verifyIssuerAuthorization($ecosystemId, $vc->getIssuer(), (string) $schema)) {
                return ['valid' => false, 'reason' => "Issuer {$vc->getIssuer()} is not authorized for schema {$schema} in ecosystem {$ecosystemId}."];
            }
        }

        $proof = $vc->getProof();
        if ($proof === null || empty($proof['proofValue'])) {
            return ['valid' => false, 'reason' => 'Missing cryptographic proof in credential.'];
        }

        // Resolve issuer
        try {
            $doc = $this->resolver->resolve($vc->getIssuer());
        } catch (\Throwable $e) {
            return ['valid' => false, 'reason' => "Issuer resolution failed: " . $e->getMessage()];
        }

        $vm = $doc->getVerificationMethod($proof['verificationMethod'] ?? '');
        if ($vm === null) {
            $vm = $doc->findVerificationMethodForAssertion();
        }

        if ($vm === null || $vm->getPublicKeyHex() === null) {
            return ['valid' => false, 'reason' => 'Issuer verification method not found.'];
        }

        $rawSig = hex2bin((string) $proof['proofValue']);
        $pubKey = hex2bin($vm->getPublicKeyHex());
        $digest = $vc->getCanonicalDigest();

        if ($rawSig === false || $pubKey === false) {
            return ['valid' => false, 'reason' => 'Malformed signature or public key.'];
        }

        $valid = sodium_crypto_sign_verify_detached($rawSig, $digest, $pubKey);
        if (!$valid) {
            return ['valid' => false, 'reason' => 'Cryptographic signature verification failed.'];
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * Create a Verifiable Presentation
     */
    public function createPresentation(
        string $holderDid,
        array $credentials,
        string $holderSecretKeyHex,
        string $nonce,
        ?string $domain = null
    ): VerifiablePresentation {
        $doc = $this->resolver->resolve($holderDid);
        $vm = $doc->findVerificationMethodForAssertion();
        if ($vm === null) {
            throw new IdentityException("No verificationMethod found on holder DID for: {$holderDid}");
        }

        $id = 'urn:uuid:' . bin2hex(random_bytes(16));
        $vp = new VerifiablePresentation(
            id: $id,
            holder: $holderDid,
            verifiableCredential: $credentials
        );

        $digest = $vp->getCanonicalDigest();
        $secretKey = hex2bin($holderSecretKeyHex);
        $signature = sodium_crypto_sign_detached($digest . ':' . $nonce, $secretKey);

        $vp->setProof([
            'type' => ProofType::ED25519_2020->value,
            'created' => gmdate('Y-m-d\TH:i:s\Z'),
            'verificationMethod' => $vm->getId(),
            'proofPurpose' => 'authentication',
            'challenge' => $nonce,
            'domain' => $domain,
            'proofValue' => bin2hex($signature),
        ]);

        return $vp;
    }

    /**
     * Verify a Verifiable Presentation
     *
     * @return array{valid: bool, reason: ?string}
     */
    public function verifyPresentation(VerifiablePresentation $vp, string $expectedNonce): array
    {
        $proof = $vp->getProof();
        if ($proof === null || empty($proof['proofValue'])) {
            return ['valid' => false, 'reason' => 'Missing proof in presentation.'];
        }

        if (($proof['challenge'] ?? '') !== $expectedNonce) {
            return ['valid' => false, 'reason' => 'Presentation nonce mismatch or challenge replay.'];
        }

        try {
            $doc = $this->resolver->resolve($vp->getHolder());
        } catch (\Throwable $e) {
            return ['valid' => false, 'reason' => "Holder DID resolution failed: " . $e->getMessage()];
        }

        $vm = $doc->getVerificationMethod($proof['verificationMethod'] ?? '');
        if ($vm === null) {
            $vm = $doc->findVerificationMethodForAssertion();
        }

        if ($vm === null || $vm->getPublicKeyHex() === null) {
            return ['valid' => false, 'reason' => 'Holder verification method not found.'];
        }

        $rawSig = hex2bin((string) $proof['proofValue']);
        $pubKey = hex2bin($vm->getPublicKeyHex());
        $digest = $vp->getCanonicalDigest();

        $valid = sodium_crypto_sign_verify_detached($rawSig, $digest . ':' . $expectedNonce, $pubKey);
        if (!$valid) {
            return ['valid' => false, 'reason' => 'Holder authentication signature verification failed.'];
        }

        // Verify embedded credentials
        foreach ($vp->getVerifiableCredentials() as $vc) {
            if ($vc instanceof VerifiableCredential) {
                $vcRes = $this->verifyCredential($vc);
                if (!$vcRes['valid']) {
                    return ['valid' => false, 'reason' => "Embedded credential verification failed: " . $vcRes['reason']];
                }
            }
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * Issue multi-attribute BBS+ signed credential
     */
    public function issueBbsCredential(
        string $issuerDid,
        array $attributes,
        string $secretKeyHex,
        string $publicKeyHex
    ): array {
        $keys = array_keys($attributes);
        $values = array_map(fn($v) => (string) $v, array_values($attributes));

        $bbsSig = $this->bbsEngine->sign($issuerDid, $values, $secretKeyHex, $publicKeyHex);

        return [
            'issuer' => $issuerDid,
            'attributes' => $attributes,
            'attributeKeys' => $keys,
            'bbsSignature' => $bbsSig,
        ];
    }

    /**
     * Create Zero-Knowledge Selective Disclosure presentation revealing only specified attribute keys
     */
    public function createSelectiveDisclosurePresentation(
        array $bbsCredential,
        array $discloseKeys,
        string $nonce
    ): SelectiveDisclosureProof {
        /** @var BbsSignature $bbsSig */
        $bbsSig = $bbsCredential['bbsSignature'];
        $attributeKeys = $bbsCredential['attributeKeys'];

        $disclosedIndices = [];
        foreach ($discloseKeys as $key) {
            $idx = array_search($key, $attributeKeys, true);
            if ($idx !== false) {
                $disclosedIndices[] = (int) $idx;
            }
        }

        return $this->bbsEngine->createProof($bbsSig, $disclosedIndices, $nonce);
    }

    /**
     * Verify Zero-Knowledge Selective Disclosure Presentation
     */
    public function verifySelectiveDisclosure(SelectiveDisclosureProof $proof, string $expectedNonce): bool
    {
        if ($proof->getNonce() !== $expectedNonce) {
            return false;
        }

        return $this->bbsEngine->verifyProof($proof);
    }

    /**
     * Create and track a StatusList2021 instance
     */
    public function createStatusList(string $id, int $capacity = 131072): StatusList2021
    {
        $list = new StatusList2021($capacity);
        $this->statusLists[$id] = $list;
        return $list;
    }

    /**
     * Revoke credential in StatusList2021
     */
    public function revokeCredential(string $listId, int $index): void
    {
        if (!isset($this->statusLists[$listId])) {
            throw new IdentityException("Status list {$listId} does not exist.");
        }

        $this->statusLists[$listId]->setStatus($index, true);
    }

    /**
     * Lookup status list by ID
     */
    public function getStatusList(string $id): ?StatusList2021
    {
        return $this->statusLists[$id] ?? null;
    }
}
