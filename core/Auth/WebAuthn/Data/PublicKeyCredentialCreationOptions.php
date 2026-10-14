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

namespace NetFusion\Core\Auth\WebAuthn\Data;

use JsonSerializable;

class PublicKeyCredentialCreationOptions implements JsonSerializable
{
    /**
     * @param array{name: string, id: string} $rp
     * @param array{id: string, name: string, displayName: string} $user
     * @param string $challenge Base64url encoded challenge
     * @param array<array{type: string, alg: int}> $pubKeyCredParams
     * @param int $timeout In milliseconds
     * @param array<array{type: string, id: string, transports?: string[]}> $excludeCredentials
     * @param array{authenticatorAttachment?: string, requireResidentKey?: bool, residentKey?: string, userVerification?: string} $authenticatorSelection
     * @param string $attestation 'none'|'indirect'|'direct'|'enterprise'
     * @param array $extensions
     */
    public function __construct(
        public readonly array $rp,
        public readonly array $user,
        public readonly string $challenge,
        public readonly array $pubKeyCredParams = [
            ['type' => 'public-key', 'alg' => -7],   // ES256 (ECDSA w/ SHA-256)
            ['type' => 'public-key', 'alg' => -257], // RS256 (RSASSA-PKCS1-v1_5 w/ SHA-256)
            ['type' => 'public-key', 'alg' => -8],   // EdDSA
            ['type' => 'public-key', 'alg' => -37],  // PS256 (RSA-PSS w/ SHA-256)
        ],
        public readonly int $timeout = 60000,
        public readonly array $excludeCredentials = [],
        public readonly array $authenticatorSelection = [
            'authenticatorAttachment' => 'platform',
            'residentKey' => 'preferred',
            'userVerification' => 'preferred',
        ],
        public readonly string $attestation = 'none',
        public readonly array $extensions = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'rp' => $this->rp,
            'user' => $this->user,
            'challenge' => $this->challenge,
            'pubKeyCredParams' => $this->pubKeyCredParams,
            'timeout' => $this->timeout,
            'excludeCredentials' => $this->excludeCredentials,
            'authenticatorSelection' => $this->authenticatorSelection,
            'attestation' => $this->attestation,
            'extensions' => $this->extensions,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
