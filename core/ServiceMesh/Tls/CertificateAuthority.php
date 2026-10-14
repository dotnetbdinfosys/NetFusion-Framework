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
use RuntimeException;

/**
 * Class CertificateAuthority
 *
 * Lightweight internal Certificate Authority (CA) using PHP OpenSSL.
 * Generates Root CA and signs workload X.509 SVID certificates with SAN URI.
 */
class CertificateAuthority
{
    private string $caCertPem = '';
    private string $caKeyPem = '';
    private int $serial = 1000;

    public function __construct(
        private readonly string $trustDomain = 'cluster.local',
        ?string $caCertPem = null,
        ?string $caKeyPem = null
    ) {
        $this->ensureOpenSslConfig();

        if ($caCertPem !== null && $caKeyPem !== null) {
            $this->caCertPem = $caCertPem;
            $this->caKeyPem = $caKeyPem;
        } else {
            $this->bootstrapRootCa();
        }
    }

    private function ensureOpenSslConfig(): void
    {
        if (getenv('OPENSSL_CONF') && file_exists((string) getenv('OPENSSL_CONF'))) {
            return;
        }

        $phpDir = defined('PHP_BINARY') ? dirname(PHP_BINARY) : '';
        $candidates = [
            $phpDir . '/extras/ssl/openssl.cnf',
            $phpDir . '/openssl.cnf',
            'C:/Program Files/Common Files/SSL/openssl.cnf',
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand)) {
                putenv("OPENSSL_CONF={$cand}");
                return;
            }
        }

        // Generate fallback openssl.cnf in temp directory
        $fallbackCnf = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'netfusion_openssl.cnf';
        if (!file_exists($fallbackCnf)) {
            $cnfData = "[ req ]\ndefault_bits = 2048\ndistinguished_name = req_dn\nstring_mask = utf8only\n[ req_dn ]\n";
            file_put_contents($fallbackCnf, $cnfData);
        }
        putenv("OPENSSL_CONF={$fallbackCnf}");
    }

    public static function create(string $trustDomain = 'cluster.local'): self
    {
        return new self($trustDomain);
    }

    public function getCaCertificatePem(): string
    {
        return $this->caCertPem;
    }

    public function getTrustDomain(): string
    {
        return $this->trustDomain;
    }

    /**
     * Issue an X.509 SVID certificate signed by this CA.
     */
    public function issueWorkloadCertificate(
        string|SpiffeId $spiffeId,
        int $ttlSeconds = 86400,
        array $dnsNames = []
    ): CertificatePair {
        $spiffe = $spiffeId instanceof SpiffeId ? $spiffeId : new SpiffeId($spiffeId);

        // Generate client/workload keypair (EC 256 or RSA 2048)
        $privKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $dn = [
            'commonName' => (string) $spiffe->getWorkloadName() ?: 'workload',
            'organizationName' => 'NetFusion Service Mesh',
            'organizationalUnitName' => $spiffe->getNamespace() ?: 'default',
        ];

        // Prepare CSR config with SAN URI
        $spiffeUri = (string) $spiffe;
        $altNames = ["URI:{$spiffeUri}"];
        foreach ($dnsNames as $dns) {
            $altNames[] = "DNS:{$dns}";
        }
        $sanString = implode(',', $altNames);

        $tempCnf = tempnam(sys_get_temp_dir(), 'cnf_');
        $cnfContent = "[ req ]\ndefault_bits = 2048\ndefault_keyfile = privkey.pem\ndistinguished_name = req_dn\nprompt = no\n[ req_dn ]\nCN = " . ($dn['commonName'] ?: 'workload') . "\n[ v3_req ]\nbasicConstraints = CA:FALSE\nkeyUsage = digitalSignature,keyEncipherment\nsubjectAltName = {$sanString}\n";
        file_put_contents($tempCnf, $cnfContent);

        // Generate client/workload keypair with config
        $privKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => $tempCnf,
        ]);

        $csr = openssl_csr_new($dn, $privKey, [
            'digest_alg' => 'sha256',
            'config' => $tempCnf,
            'req_extensions' => 'v3_req',
        ]);

        $days = (int) ceil($ttlSeconds / 86400);
        $days = max(1, $days);

        $caKey = openssl_pkey_get_private($this->caKeyPem);
        $caCert = openssl_x509_read($this->caCertPem);

        $signedCert = openssl_csr_sign($csr, $caCert, $caKey, $days, [
            'digest_alg' => 'sha256',
            'config' => $tempCnf,
            'x509_extensions' => 'v3_req',
        ], ++$this->serial);

        if ($signedCert === false) {
            @unlink($tempCnf);
            throw new RuntimeException("Failed to issue workload certificate: " . openssl_error_string());
        }

        openssl_x509_export($signedCert, $certOut);
        openssl_pkey_export($privKey, $keyOut, null, ['config' => $tempCnf]);
        @unlink($tempCnf);

        $expiresAt = time() + $ttlSeconds;

        return new CertificatePair(
            certificatePem: $certOut,
            privateKeyPem: $keyOut,
            spiffeId: $spiffe,
            expiresAt: $expiresAt,
            metadata: ['serial' => $this->serial, 'dns' => $dnsNames]
        );
    }

    private function bootstrapRootCa(): void
    {
        $commonName = "NetFusion Mesh Root CA ({$this->trustDomain})";
        $dn = [
            'commonName' => $commonName,
            'organizationName' => 'NetFusion Service Mesh',
            'countryName' => 'BD',
        ];

        $tempCnf = tempnam(sys_get_temp_dir(), 'ca_cnf_');
        $caCnfContent = "[ req ]\ndefault_bits = 2048\ndefault_keyfile = privkey.pem\ndistinguished_name = req_dn\nprompt = no\n[ req_dn ]\nCN = {$commonName}\nO = NetFusion Service Mesh\nC = BD\n[ v3_ca ]\nbasicConstraints = critical,CA:TRUE\nkeyUsage = critical,keyCertSign,cRLSign\n";
        file_put_contents($tempCnf, $caCnfContent);

        $privKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => $tempCnf,
        ]);

        $csr = openssl_csr_new($dn, $privKey, [
            'digest_alg' => 'sha256',
            'config' => $tempCnf,
            'req_extensions' => 'v3_ca',
        ]);

        if ($csr === false) {
            @unlink($tempCnf);
            throw new RuntimeException("Failed to generate Root CA CSR: " . openssl_error_string());
        }

        $selfSigned = openssl_csr_sign($csr, null, $privKey, 3650, [
            'digest_alg' => 'sha256',
            'config' => $tempCnf,
            'x509_extensions' => 'v3_ca',
        ], 1);

        if ($selfSigned === false) {
            @unlink($tempCnf);
            throw new RuntimeException("Failed to self-sign Root CA: " . openssl_error_string());
        }

        openssl_x509_export($selfSigned, $caOut);
        openssl_pkey_export($privKey, $keyOut, null, ['config' => $tempCnf]);
        @unlink($tempCnf);

        $this->caCertPem = $caOut;
        $this->caKeyPem = $keyOut;
    }
}
