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

/**
 * Class MtlsContext
 *
 * Configures mutual TLS (mTLS) stream SSL contexts for clients and servers.
 */
class MtlsContext
{
    private array $options = [];
    private ?string $tempCertFile = null;
    private ?string $tempKeyFile = null;
    private ?string $tempCaFile = null;

    public function __construct(
        private readonly ?CertificatePair $clientOrServerCert = null,
        private readonly ?string $trustedCaPem = null,
        private readonly bool $requireClientCert = true,
        private readonly bool $verifyPeer = true
    ) {
        $this->buildSslOptions();
    }

    public static function forClient(
        CertificatePair $clientCert,
        string $trustedCaPem,
        bool $verifyPeer = true
    ): self {
        return new self($clientCert, $trustedCaPem, true, $verifyPeer);
    }

    public static function forServer(
        CertificatePair $serverCert,
        string $trustedCaPem,
        bool $requireClientCert = true
    ): self {
        return new self($serverCert, $trustedCaPem, $requireClientCert, true);
    }

    public function toStreamContext()
    {
        return stream_context_create(['ssl' => $this->options]);
    }

    public function getSslOptions(): array
    {
        return $this->options;
    }

    private function buildSslOptions(): void
    {
        $opts = [
            'verify_peer' => $this->verifyPeer,
            'verify_peer_name' => false, // Service mesh routes via IP/VIP; identity verified via SPIFFE SAN
            'allow_self_signed' => true,  // For internal custom mesh CA
        ];

        if ($this->trustedCaPem !== null) {
            $this->tempCaFile = tempnam(sys_get_temp_dir(), 'mesh_ca_');
            file_put_contents($this->tempCaFile, $this->trustedCaPem);
            $opts['cafile'] = $this->tempCaFile;
        }

        if ($this->clientOrServerCert !== null) {
            $this->tempCertFile = tempnam(sys_get_temp_dir(), 'mesh_cert_');
            $this->tempKeyFile = tempnam(sys_get_temp_dir(), 'mesh_key_');

            file_put_contents($this->tempCertFile, $this->clientOrServerCert->getCertificatePem());
            file_put_contents($this->tempKeyFile, $this->clientOrServerCert->getPrivateKeyPem());

            $opts['local_cert'] = $this->tempCertFile;
            $opts['local_pk'] = $this->tempKeyFile;
        }

        if ($this->requireClientCert) {
            $opts['fail_if_no_peer_cert'] = true;
            $opts['verify_depth'] = 5;
        }

        $this->options = $opts;
    }

    public function __destruct()
    {
        if ($this->tempCertFile && file_exists($this->tempCertFile)) {
            @unlink($this->tempCertFile);
        }
        if ($this->tempKeyFile && file_exists($this->tempKeyFile)) {
            @unlink($this->tempKeyFile);
        }
        if ($this->tempCaFile && file_exists($this->tempCaFile)) {
            @unlink($this->tempCaFile);
        }
    }
}
