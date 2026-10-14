<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\ServiceMesh\Spiffe;

use InvalidArgumentException;
use RuntimeException;

/**
 * Class X509Svid
 *
 * Represents an X.509 SPIFFE Verifiable Identity Document (X.509-SVID).
 * Extracts SPIFFE ID from Subject Alternative Name (SAN) URI.
 */
class X509Svid
{
    private readonly SpiffeId $spiffeId;
    private readonly int $validFrom;
    private readonly int $validTo;
    private readonly string $serialNumber;

    public function __construct(
        private readonly string $certPem,
        private readonly ?string $privateKeyPem = null
    ) {
        $parsed = openssl_x509_parse($certPem);
        if ($parsed === false) {
            throw new InvalidArgumentException("Unable to parse X.509 certificate PEM.");
        }

        $this->validFrom = (int) ($parsed['validFrom_time_t'] ?? 0);
        $this->validTo = (int) ($parsed['validTo_time_t'] ?? 0);
        $this->serialNumber = (string) ($parsed['serialNumberHex'] ?? $parsed['serialNumber'] ?? '');

        // Extract SPIFFE ID from Subject Alternative Name (SAN)
        $spiffeUri = $this->extractSpiffeUri($parsed);
        if ($spiffeUri === null) {
            throw new InvalidArgumentException("X.509 certificate does not contain a valid SPIFFE ID in Subject Alternative Name (SAN).");
        }

        $this->spiffeId = new SpiffeId($spiffeUri);
    }

    public static function fromPem(string $certPem, ?string $privateKeyPem = null): self
    {
        return new self($certPem, $privateKeyPem);
    }

    public function getSpiffeId(): SpiffeId
    {
        return $this->spiffeId;
    }

    public function getCertificatePem(): string
    {
        return $this->certPem;
    }

    public function getPrivateKeyPem(): ?string
    {
        return $this->privateKeyPem;
    }

    public function getValidFrom(): int
    {
        return $this->validFrom;
    }

    public function getValidTo(): int
    {
        return $this->validTo;
    }

    public function isExpired(int $now = 0): bool
    {
        $now = $now ?: time();
        return $now > $this->validTo;
    }

    public function isValidAt(int $timestamp): bool
    {
        return $timestamp >= $this->validFrom && $timestamp <= $this->validTo;
    }

    public function getSerialNumber(): string
    {
        return $this->serialNumber;
    }

    public function verify(TrustBundle $bundle): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        return $bundle->verifyCertificate($this->certPem, $this->spiffeId->getTrustDomain());
    }

    private function extractSpiffeUri(array $parsed): ?string
    {
        $extensions = $parsed['extensions'] ?? [];
        $san = $extensions['subjectAltName'] ?? null;

        if ($san !== null) {
            // Examples: "URI:spiffe://cluster.local/ns/prod/sa/payment, DNS:service"
            $parts = explode(',', $san);
            foreach ($parts as $part) {
                $part = trim($part);
                if (str_starts_with($part, 'URI:spiffe://')) {
                    return substr($part, 4);
                }
                if (str_starts_with($part, 'spiffe://')) {
                    return $part;
                }
            }
        }

        // Fallback: Check custom subject field or raw text if SAN parser omitted it
        if (isset($parsed['subject']['UNDEF']) && str_starts_with($parsed['subject']['UNDEF'], 'spiffe://')) {
            return $parsed['subject']['UNDEF'];
        }

        return null;
    }
}
