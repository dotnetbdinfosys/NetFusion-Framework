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

use NetFusion\Core\Security\Identity\Exceptions\IdentityException;
use NetFusion\Core\Security\Identity\Exceptions\InvalidDidDocumentException;

class DidResolver
{
    /** @var array<string, DidDocument> */
    protected array $registry = [];
    protected DidGenerator $generator;

    public function __construct(?DidGenerator $generator = null)
    {
        $this->generator = $generator ?? new DidGenerator();
    }

    /**
     * Register a DID document directly into the local resolver cache/registry.
     */
    public function register(DidDocument $document): void
    {
        $this->registry[$document->getId()] = $document;
    }

    /**
     * Resolve a DID URI to its DidDocument.
     */
    public function resolve(string $did): DidDocument
    {
        if (isset($this->registry[$did])) {
            return $this->registry[$did];
        }

        // Handle did:key natively
        if (str_starts_with($did, 'did:key:')) {
            return $this->resolveDidKey($did);
        }

        throw new IdentityException("Unable to resolve DID: {$did}. DID document not found in registry.");
    }

    /**
     * Deterministically resolve a did:key identifier without network lookup
     */
    protected function resolveDidKey(string $did): DidDocument
    {
        $identifier = substr($did, strlen('did:key:'));
        if (!str_starts_with($identifier, 'z')) {
            throw new InvalidDidDocumentException("Unsupported did:key encoding; expected base58btc prefixed with 'z'");
        }

        $decoded = $this->generator->base58Decode(substr($identifier, 1));
        if (strlen($decoded) < 2) {
            throw new InvalidDidDocumentException("Invalid did:key multicodec payload.");
        }

        // Multicodec 0xed01 is Ed25519 public key (2 bytes prefix + 32 bytes key)
        $prefix = substr($decoded, 0, 2);
        $rawKey = substr($decoded, 2);

        $keyId = $did . '#' . $identifier;
        $vm = new VerificationMethod(
            id: $keyId,
            type: 'Ed25519VerificationKey2020',
            controller: $did,
            publicKeyMultibase: $identifier,
            publicKeyHex: bin2hex($rawKey)
        );

        $doc = new DidDocument(
            id: $did,
            verificationMethod: [$keyId => $vm],
            authentication: [$keyId],
            assertionMethod: [$keyId]
        );

        $this->registry[$did] = $doc;
        return $doc;
    }
}
