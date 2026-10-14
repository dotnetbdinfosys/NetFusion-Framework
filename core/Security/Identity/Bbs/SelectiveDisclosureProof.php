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

use JsonSerializable;

class SelectiveDisclosureProof implements JsonSerializable
{
    /**
     * @param array<int, string> $revealedMessages Map of index => revealed value
     * @param int[] $undisclosedIndices List of undisclosed indices
     */
    public function __construct(
        protected string $issuerDid,
        protected array $revealedMessages,
        protected array $undisclosedIndices,
        protected string $nonce,
        protected string $proofValue,
        protected string $publicKeyHex
    ) {
    }

    public function getIssuerDid(): string
    {
        return $this->issuerDid;
    }

    public function getRevealedMessages(): array
    {
        return $this->revealedMessages;
    }

    public function getUndisclosedIndices(): array
    {
        return $this->undisclosedIndices;
    }

    public function getNonce(): string
    {
        return $this->nonce;
    }

    public function getProofValue(): string
    {
        return $this->proofValue;
    }

    public function getPublicKeyHex(): string
    {
        return $this->publicKeyHex;
    }

    public function toArray(): array
    {
        return [
            'type' => 'BbsBlsSignatureProof2020',
            'issuerDid' => $this->issuerDid,
            'revealedMessages' => $this->revealedMessages,
            'undisclosedIndices' => $this->undisclosedIndices,
            'nonce' => $this->nonce,
            'proofValue' => $this->proofValue,
            'publicKeyHex' => $this->publicKeyHex,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
