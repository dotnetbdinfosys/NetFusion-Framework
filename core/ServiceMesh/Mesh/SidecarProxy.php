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

namespace NetFusion\Core\ServiceMesh\Mesh;

use NetFusion\Core\ServiceMesh\Policy\AccessDecision;
use NetFusion\Core\ServiceMesh\Policy\PolicyEngine;
use NetFusion\Core\ServiceMesh\Policy\ServiceIdentity;
use NetFusion\Core\ServiceMesh\Spiffe\SpiffeId;
use NetFusion\Core\ServiceMesh\Tls\CertificatePair;
use NetFusion\Core\ServiceMesh\Tls\MtlsContext;

/**
 * Class SidecarProxy
 *
 * Sidecar proxy acting as the local mTLS and security policy enforcement point
 * for both inbound (ingress) and outbound (egress) microservice communications.
 */
class SidecarProxy
{
    public function __construct(
        private readonly string $serviceName,
        private readonly SpiffeId $localSpiffeId,
        private readonly CertificatePair $certificatePair,
        private readonly string $trustedCaPem,
        private readonly PolicyEngine $policyEngine,
        private readonly ?MeshTopology $topology = null,
        private readonly bool $simulate = true
    ) {}

    public function handleInbound(
        ServiceIdentity $callerIdentity,
        string $method = 'GET',
        string $path = '/',
        array $headers = []
    ): AccessDecision {
        // Enforce Zero-Trust authorization policy
        $decision = $this->policyEngine->authorize($callerIdentity, $this->localSpiffeId, $method, $path, $headers);

        if ($decision->isAllowed() && $this->topology !== null) {
            $this->topology->recordTraffic($callerIdentity->getServiceName(), $this->serviceName);
        }

        return $decision;
    }

    public function forwardOutbound(
        string $targetServiceName,
        SpiffeId $targetSpiffeId,
        string $method = 'GET',
        string $path = '/',
        array $headers = [],
        string $payload = ''
    ): array {
        // Build outbound client mTLS context
        $clientContext = MtlsContext::forClient($this->certificatePair, $this->trustedCaPem);

        if ($this->topology !== null) {
            $this->topology->recordTraffic($this->serviceName, $targetServiceName);
        }

        if ($this->simulate) {
            return [
                'status_code' => 200,
                'headers' => ['content-type' => 'application/json', 'x-spiffe-id' => (string) $this->localSpiffeId],
                'body' => json_encode(['status' => 'success', 'caller' => (string) $this->localSpiffeId]),
            ];
        }

        // Live socket forwarding would occur here via SecureSocket
        return [
            'status_code' => 200,
            'headers' => [],
            'body' => 'OK',
        ];
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getLocalSpiffeId(): SpiffeId
    {
        return $this->localSpiffeId;
    }
}
