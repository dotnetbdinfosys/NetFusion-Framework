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
use NetFusion\Core\ServiceMesh\Spiffe\X509Svid;
use RuntimeException;

/**
 * Class SecureSocket
 *
 * Stream-based mTLS socket connection handling mutual authentication
 * and extracting peer SPIFFE identity from the negotiated TLS session.
 */
class SecureSocket
{
    private $stream = null;
    private ?X509Svid $peerSvid = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly MtlsContext $context,
        private readonly float $timeout = 5.0,
        private readonly bool $simulate = false
    ) {}

    public function connect(): self
    {
        if ($this->simulate) {
            // Simulated connection for testing environments without raw TCP listening
            return $this;
        }

        $remoteSocket = "tls://{$this->host}:{$this->port}";
        $stream = @stream_socket_client(
            $remoteSocket,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $this->context->toStreamContext()
        );

        if (!$stream) {
            throw new RuntimeException("mTLS connection failed to {$remoteSocket}: [{$errno}] {$errstr}");
        }

        $this->stream = $stream;
        $this->extractPeerCertificate();

        return $this;
    }

    public function getPeerSvid(): ?X509Svid
    {
        return $this->peerSvid;
    }

    public function getPeerSpiffeId(): ?SpiffeId
    {
        return $this->peerSvid?->getSpiffeId();
    }

    public function write(string $data): int
    {
        if ($this->simulate) {
            return strlen($data);
        }

        if (!$this->stream) {
            throw new RuntimeException("Socket is not connected.");
        }

        $written = fwrite($this->stream, $data);
        if ($written === false) {
            throw new RuntimeException("Failed to write to mTLS socket.");
        }
        return $written;
    }

    public function read(int $length = 8192): string
    {
        if ($this->simulate) {
            return "HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nOK";
        }

        if (!$this->stream) {
            throw new RuntimeException("Socket is not connected.");
        }

        $data = fread($this->stream, $length);
        return $data === false ? '' : $data;
    }

    public function close(): void
    {
        if ($this->stream && is_resource($this->stream)) {
            fclose($this->stream);
            $this->stream = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->simulate || ($this->stream !== null && is_resource($this->stream));
    }

    private function extractPeerCertificate(): void
    {
        if (!$this->stream) return;

        $params = stream_context_get_params($this->stream);
        $peerCert = $params['options']['ssl']['peer_certificate'] ?? null;

        if ($peerCert) {
            openssl_x509_export($peerCert, $certPem);
            try {
                $this->peerSvid = X509Svid::fromPem($certPem);
            } catch (\Throwable) {
                $this->peerSvid = null;
            }
        }
    }

    public function setSimulatedPeerSvid(X509Svid $svid): void
    {
        $this->peerSvid = $svid;
    }

    public function __destruct()
    {
        $this->close();
    }
}
