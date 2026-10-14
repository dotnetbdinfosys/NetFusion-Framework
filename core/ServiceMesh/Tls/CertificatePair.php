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
 * Class CertificatePair
 *
 * Encapsulates an X.509 certificate and its private key.
 */
class CertificatePair
{
    public function __construct(
        private readonly string $certificatePem,
        private readonly string $privateKeyPem,
        private readonly ?SpiffeId $spiffeId = null,
        private readonly int $expiresAt = 0,
        private readonly array $metadata = []
    ) {}

    public function getCertificatePem(): string
    {
        return $this->certificatePem;
    }

    public function getPrivateKeyPem(): string
    {
        return $this->privateKeyPem;
    }

    public function getSpiffeId(): ?SpiffeId
    {
        return $this->spiffeId;
    }

    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }

    public function isExpired(int $graceSeconds = 0): bool
    {
        return (time() + $graceSeconds) >= $this->expiresAt;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function writeToTempFiles(): array
    {
        $certFile = tempnam(sys_get_temp_dir(), 'mtls_cert_');
        $keyFile = tempnam(sys_get_temp_dir(), 'mtls_key_');

        file_put_contents($certFile, $this->certificatePem);
        file_put_contents($keyFile, $this->privateKeyPem);

        return [$certFile, $keyFile];
    }
}
