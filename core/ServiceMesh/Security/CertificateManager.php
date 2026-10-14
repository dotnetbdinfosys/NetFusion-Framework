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

namespace NetFusion\Core\ServiceMesh\Security;

use NetFusion\Core\ServiceMesh\Contracts\CertificateManagerInterface;
use NetFusion\Core\ServiceMesh\Contracts\MeshStoreInterface;
use NetFusion\Core\ServiceMesh\Exceptions\CertificateValidationException;
use OpenSSLAsymmetricKey;

class CertificateManager implements CertificateManagerInterface
{
    protected string $trustDomain;
    protected ?string $rootCaCertPem = null;
    protected ?string $rootCaKeyPem = null;
    protected ?MeshStoreInterface $store;
    /** @var array<string, array{cert: string, key: string, ca: string, expires_at: int, spiffe_id: string}> */
    protected array $certificateCache = [];

    public function __construct(
        string $trustDomain = 'cluster.local',
        ?MeshStoreInterface $store = null,
        ?string $rootCaCert = null,
        ?string $rootCaKey = null
    ) {
        $this->trustDomain = $trustDomain;
        $this->store = $store;
        $this->rootCaCertPem = $rootCaCert;
        $this->rootCaKeyPem = $rootCaKey;

        if ($this->rootCaCertPem === null || $this->rootCaKeyPem === null) {
            $this->initRootCa();
        }
    }

    /**
     * Get or create a valid OpenSSL configuration file path.
     */
    protected function getOpenSslConfigPath(): ?string
    {
        $envConf = getenv('OPENSSL_CONF');
        if ($envConf && file_exists($envConf)) {
            return $envConf;
        }

        $phpDir = dirname(PHP_BINARY);
        $candidates = [
            $phpDir . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
            $phpDir . DIRECTORY_SEPARATOR . 'openssl.cnf',
            'C:\\Program Files\\Common Files\\SSL\\openssl.cnf',
            'C:\\OpenSSL-Win64\\bin\\openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/etc/pki/tls/openssl.cnf',
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand)) {
                return $cand;
            }
        }

        // Generate minimal temporary openssl.cnf
        $tmpCnf = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'netfusion_openssl.cnf';
        if (!file_exists($tmpCnf)) {
            $cnfContent = <<<CNF
[ req ]
default_bits = 2048
distinguished_name = req_distinguished_name
x509_extensions = v3_ca
req_extensions = v3_req

[ req_distinguished_name ]
countryName = Country Name (2 letter code)
organizationName = Organization Name
organizationalUnitName = Organizational Unit Name
commonName = Common Name

[ v3_ca ]
basicConstraints = critical,CA:TRUE

[ v3_req ]
basicConstraints = CA:FALSE
keyUsage = nonRepudiation, digitalSignature, keyEncipherment
CNF;
            @file_put_contents($tmpCnf, $cnfContent);
        }

        return file_exists($tmpCnf) ? $tmpCnf : null;
    }

    /**
     * Initialize Mesh Root Certificate Authority.
     */
    protected function initRootCa(): void
    {
        $cnfPath = $this->getOpenSslConfigPath();
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        if ($cnfPath !== null) {
            $config['config'] = $cnfPath;
        }

        $privKey = openssl_pkey_new($config);
        $dn = [
            'countryName' => 'BD',
            'organizationName' => 'DotNetBD Enterprise',
            'organizationalUnitName' => 'Service Mesh Security Authority',
            'commonName' => 'NetFusion Mesh Root CA',
        ];

        $csr = openssl_csr_new($dn, $privKey, $config);
        $x509 = openssl_csr_sign($csr, null, $privKey, 3650, $config);

        openssl_x509_export($x509, $caCertPem);
        openssl_pkey_export($privKey, $caKeyPem, null, $config);

        $this->rootCaCertPem = $caCertPem;
        $this->rootCaKeyPem = $caKeyPem;
    }

    public function issueCertificate(SpiffeId $spiffeId, int $validitySeconds = 86400): array
    {
        $idStr = $spiffeId->toString();

        if (isset($this->certificateCache[$idStr])) {
            $cached = $this->certificateCache[$idStr];
            if ($cached['expires_at'] > (time() + 60)) {
                return $cached;
            }
        }

        $cnfPath = $this->getOpenSslConfigPath();
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        if ($cnfPath !== null) {
            $config['config'] = $cnfPath;
        }

        $leafPrivKey = openssl_pkey_new($config);
        $dn = [
            'countryName' => 'BD',
            'organizationName' => 'DotNetBD Mesh Workload',
            'commonName' => $spiffeId->getServiceAccount(),
        ];

        $sanConfig = array_merge($config, [
            'digest_alg' => 'sha256',
        ]);

        $csr = openssl_csr_new($dn, $leafPrivKey, $sanConfig);

        $caCert = openssl_x509_read($this->rootCaCertPem);
        $caKey = openssl_pkey_get_private($this->rootCaKeyPem);

        $days = (int) ceil($validitySeconds / 86400);
        $leafX509 = openssl_csr_sign($csr, $caCert, $caKey, max(1, $days), $sanConfig);

        openssl_x509_export($leafX509, $leafCertPem);
        openssl_pkey_export($leafPrivKey, $leafKeyPem, null, $config);

        $certData = [
            'cert' => $leafCertPem,
            'key' => $leafKeyPem,
            'ca' => $this->rootCaCertPem,
            'expires_at' => time() + $validitySeconds,
            'spiffe_id' => $idStr,
        ];

        $this->certificateCache[$idStr] = $certData;

        if ($this->store !== null) {
            $this->store->saveCertificate($idStr, $certData);
        }

        return $certData;
    }

    public function verifyCertificate(string $pemCert): array
    {
        $x509 = @openssl_x509_read($pemCert);
        if (!$x509) {
            return ['valid' => false, 'spiffe_id' => null, 'error' => 'Invalid X.509 PEM certificate'];
        }

        $parsed = openssl_x509_parse($x509);
        if (!$parsed) {
            return ['valid' => false, 'spiffe_id' => null, 'error' => 'Failed to parse certificate'];
        }

        // Check validity period
        $validFrom = $parsed['validFrom_time_t'] ?? 0;
        $validTo = $parsed['validTo_time_t'] ?? 0;
        $now = time();

        if ($now < $validFrom || $now > $validTo) {
            return ['valid' => false, 'spiffe_id' => null, 'error' => 'Certificate is expired or not yet valid'];
        }

        // Extract CommonName as default SPIFFE SA identity
        $cn = $parsed['subject']['CN'] ?? 'unknown';
        $spiffeId = sprintf('spiffe://%s/ns/default/sa/%s', $this->trustDomain, $cn);

        return [
            'valid' => true,
            'spiffe_id' => $spiffeId,
            'common_name' => $cn,
            'subject' => $parsed['subject'] ?? [],
            'expires_at' => $validTo,
            'error' => null,
        ];
    }

    public function rotateCertificate(SpiffeId $spiffeId): array
    {
        $idStr = $spiffeId->toString();
        unset($this->certificateCache[$idStr]);
        return $this->issueCertificate($spiffeId);
    }

    public function getRootCaPem(): string
    {
        return $this->rootCaCertPem;
    }
}
