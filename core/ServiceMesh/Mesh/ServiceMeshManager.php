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

use NetFusion\Core\ServiceMesh\Policy\MeshPolicy;
use NetFusion\Core\ServiceMesh\Policy\PolicyEngine;
use NetFusion\Core\ServiceMesh\Spiffe\SpiffeId;
use NetFusion\Core\ServiceMesh\Spiffe\TrustBundle;
use NetFusion\Core\ServiceMesh\Spiffe\WorkloadAttestor;
use NetFusion\Core\ServiceMesh\Tls\CertificateAuthority;
use NetFusion\Core\ServiceMesh\Tls\CertificatePair;
use NetFusion\Core\ServiceMesh\Tls\CertificateRotator;

/**
 * Class ServiceMeshManager
 *
 * Central Orchestrator and Facade for the NetFusion Zero-Trust Service Mesh.
 */
class ServiceMeshManager
{
    private static ?self $instance = null;

    private readonly CertificateAuthority $ca;
    private readonly TrustBundle $trustBundle;
    private readonly WorkloadAttestor $attestor;
    private readonly CertificateRotator $rotator;
    private readonly PolicyEngine $policyEngine;
    private readonly MeshTopology $topology;

    public function __construct(
        private readonly string $trustDomain = 'cluster.local',
        ?CertificateAuthority $ca = null
    ) {
        $this->ca = $ca ?? CertificateAuthority::create($this->trustDomain);
        $this->trustBundle = new TrustBundle([$this->trustDomain => $this->ca->getCaCertificatePem()]);
        $this->attestor = new WorkloadAttestor($this->trustDomain);
        $this->rotator = new CertificateRotator($this->ca);
        $this->policyEngine = new PolicyEngine();
        $this->topology = new MeshTopology();
    }

    public static function create(string $trustDomain = 'cluster.local'): self
    {
        return new self($trustDomain);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function getTrustDomain(): string
    {
        return $this->trustDomain;
    }

    public function getCa(): CertificateAuthority
    {
        return $this->ca;
    }

    public function getTrustBundle(): TrustBundle
    {
        return $this->trustBundle;
    }

    public function getAttestor(): WorkloadAttestor
    {
        return $this->attestor;
    }

    public function getRotator(): CertificateRotator
    {
        return $this->rotator;
    }

    public function getPolicyEngine(): PolicyEngine
    {
        return $this->policyEngine;
    }

    public function getTopology(): MeshTopology
    {
        return $this->topology;
    }

    /**
     * Register a microservice workload, issue its X.509 SVID, and add it to the topology.
     */
    public function registerService(
        string $serviceName,
        string $namespace = 'default',
        string $host = '127.0.0.1',
        int $port = 8080,
        int $mtlsPort = 8443
    ): CertificatePair {
        $spiffeId = SpiffeId::of($this->trustDomain, "/ns/{$namespace}/sa/{$serviceName}");

        $certPair = $this->ca->issueWorkloadCertificate($spiffeId, 86400, [$serviceName, "{$serviceName}.{$namespace}"]);
        $this->rotator->register($serviceName, $certPair);

        $node = MeshNode::create($serviceName, $spiffeId, $host, $port, $mtlsPort);
        $this->topology->addNode($node);

        return $certPair;
    }

    public function createSidecar(string $serviceName, bool $simulate = true): SidecarProxy
    {
        $cert = $this->rotator->getCertificate($serviceName);
        if ($cert === null) {
            $cert = $this->registerService($serviceName);
        }

        return new SidecarProxy(
            serviceName: $serviceName,
            localSpiffeId: $cert->getSpiffeId(),
            certificatePair: $cert,
            trustedCaPem: $this->ca->getCaCertificatePem(),
            policyEngine: $this->policyEngine,
            topology: $this->topology,
            simulate: $simulate
        );
    }

    public function definePolicy(string $serviceName): MeshPolicy
    {
        $policy = MeshPolicy::create($serviceName);
        $this->policyEngine->registerPolicy($policy);
        return $policy;
    }

    public function getStats(): array
    {
        return [
            'trust_domain' => $this->trustDomain,
            'active_nodes' => count($this->topology->getNodes()),
            'policies' => $this->policyEngine->getPolicyCount(),
            'certificates_tracked' => $this->rotator->getActiveCount(),
            'rotations' => $this->rotator->getRotationCount(),
        ];
    }
}
