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

use RuntimeException;

/**
 * Class TrustBundle
 *
 * Manages trusted root CA certificates per trust domain (SPIFFE Trust Domain Bundle).
 */
class TrustBundle
{
    /** @var array<string, string[]> trustDomain => array of PEM certificates */
    private array $bundles = [];

    public function __construct(array $initialBundles = [])
    {
        foreach ($initialBundles as $td => $certs) {
            $this->bundles[strtolower($td)] = is_array($certs) ? $certs : [$certs];
        }
    }

    public function addCertificate(string $trustDomain, string $certificatePem): self
    {
        $td = strtolower($trustDomain);
        $this->bundles[$td] ??= [];
        if (!in_array($certificatePem, $this->bundles[$td], true)) {
            $this->bundles[$td][] = $certificatePem;
        }
        return $this;
    }

    /**
     * @return string[]
     */
    public function getCertificates(string $trustDomain): array
    {
        return $this->bundles[strtolower($trustDomain)] ?? [];
    }

    public function hasTrustDomain(string $trustDomain): bool
    {
        return !empty($this->bundles[strtolower($trustDomain)]);
    }

    public function verifyCertificate(string $certPem, string $trustDomain): bool
    {
        $trustedCas = $this->getCertificates($trustDomain);
        if (empty($trustedCas)) {
            return false;
        }

        $tempCaFile = tempnam(sys_get_temp_dir(), 'ca_bundle_');
        file_put_contents($tempCaFile, implode("\n", $trustedCas));

        $tempCertFile = tempnam(sys_get_temp_dir(), 'leaf_cert_');
        file_put_contents($tempCertFile, $certPem);

        try {
            $certResource = openssl_x509_read($certPem);
            if ($certResource === false) {
                return false;
            }

            // Verify with openssl_x509_checkpurpose using trusted CA
            $valid = openssl_x509_checkpurpose($certResource, X509_PURPOSE_SSL_SERVER, [$tempCaFile]);
            return $valid === true;
        } finally {
            @unlink($tempCaFile);
            @unlink($tempCertFile);
        }
    }

    public function exportBundle(string $trustDomain): string
    {
        return implode("\n", $this->getCertificates($trustDomain));
    }

    public function getTrustDomains(): array
    {
        return array_keys($this->bundles);
    }
}
