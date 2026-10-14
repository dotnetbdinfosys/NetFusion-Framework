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

namespace NetFusion\Core\ServiceMesh\Tls;

use NetFusion\Core\ServiceMesh\Spiffe\SpiffeId;

/**
 * Class CertificateRotator
 *
 * Tracks active certificates and automatically rotates them before expiration.
 */
class CertificateRotator
{
    /** @var array<string, CertificatePair> */
    private array $activeCerts = [];
    private int $rotationCount = 0;

    public function __construct(
        private readonly CertificateAuthority $ca,
        private readonly int $rotationGraceSeconds = 300, // Rotate when 5 mins left
        private readonly int $defaultTtl = 86400
    ) {}

    public function register(string $serviceKey, CertificatePair $pair): void
    {
        $this->activeCerts[$serviceKey] = $pair;
    }

    public function getCertificate(string $serviceKey): ?CertificatePair
    {
        return $this->activeCerts[$serviceKey] ?? null;
    }

    public function checkAndRotate(string $serviceKey): CertificatePair
    {
        $current = $this->activeCerts[$serviceKey] ?? null;

        if ($current === null || $current->isExpired($this->rotationGraceSeconds)) {
            $spiffeId = $current?->getSpiffeId() ?? SpiffeId::of($this->ca->getTrustDomain(), "/sa/{$serviceKey}");
            $dns = $current?->getMetadata()['dns'] ?? [$serviceKey];

            $newPair = $this->ca->issueWorkloadCertificate($spiffeId, $this->defaultTtl, $dns);
            $this->activeCerts[$serviceKey] = $newPair;
            $this->rotationCount++;

            return $newPair;
        }

        return $current;
    }

    public function rotateAll(): array
    {
        $rotated = [];
        foreach (array_keys($this->activeCerts) as $serviceKey) {
            $rotated[$serviceKey] = $this->checkAndRotate($serviceKey);
        }
        return $rotated;
    }

    public function getRotationCount(): int
    {
        return $this->rotationCount;
    }

    public function getActiveCount(): int
    {
        return count($this->activeCerts);
    }
}
