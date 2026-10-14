<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Consul;

use NetFusion\Core\Consul\Catalog\HealthCheckEngine;
use NetFusion\Core\Consul\Catalog\ServiceCatalog;
use NetFusion\Core\Consul\Dns\DnsPacketParser;
use NetFusion\Core\Consul\Dns\DnsResolver;
use NetFusion\Core\Consul\Enums\CheckStatus;
use NetFusion\Core\Consul\Enums\CheckType;
use NetFusion\Core\Consul\Enums\DnsRecordType;
use NetFusion\Core\Consul\Enums\NodeStatus;
use NetFusion\Core\Consul\Gossip\GossipDisseminator;
use NetFusion\Core\Consul\Gossip\SwimFailureDetector;
use NetFusion\Core\Consul\Models\DnsMessage;
use NetFusion\Core\Consul\Models\GossipNode;
use NetFusion\Core\Consul\Models\ServiceCheck;
use NetFusion\Core\Consul\Models\ServiceInstance;

/**
 * Master Enterprise Consul Facade & Orchestrator.
 * Unifies Service Discovery, Dynamic DNS Resolution, SWIM Gossip Cluster, and Health Probing.
 */
class ConsulManager
{
    /** @var array<string, mixed> */
    protected array $config;
    protected ServiceCatalog $catalog;
    protected HealthCheckEngine $healthEngine;
    protected GossipNode $localNode;
    protected SwimFailureDetector $failureDetector;
    protected GossipDisseminator $disseminator;
    protected DnsResolver $dnsResolver;
    protected DnsPacketParser $dnsParser;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;

        $nodeId = (string)($config['node_id'] ?? 'node-' . getmypid());
        $nodeName = (string)($config['node_name'] ?? gethostname() ?: 'consul-node');
        $nodeAddress = (string)($config['bind_addr'] ?? '127.0.0.1');
        $nodePort = (int)($config['gossip_port'] ?? 8301);

        $this->localNode = new GossipNode($nodeId, $nodeName, $nodeAddress, $nodePort, NodeStatus::ALIVE, 0);

        $this->catalog = new ServiceCatalog();
        $this->healthEngine = new HealthCheckEngine($this->catalog);

        $this->failureDetector = new SwimFailureDetector($this->localNode, [
            'pingTimeout' => (float)($config['ping_timeout'] ?? 0.5),
            'indirectPingTimeout' => (float)($config['indirect_ping_timeout'] ?? 1.0),
            'suspectTimeout' => (float)($config['suspect_timeout'] ?? 3.0),
            'indirectPingPeersCount' => (int)($config['indirect_peers'] ?? 3),
        ]);

        $this->disseminator = new GossipDisseminator($this->localNode, $this->failureDetector);

        $dnsDomain = (string)($config['dns_domain'] ?? 'consul');
        $dnsTtl = (int)($config['dns_ttl'] ?? 0);
        $this->dnsResolver = new DnsResolver($this->catalog, $dnsDomain, $dnsTtl);
        $this->dnsParser = new DnsPacketParser();
    }

    public function getCatalog(): ServiceCatalog
    {
        return $this->catalog;
    }

    public function getHealthEngine(): HealthCheckEngine
    {
        return $this->healthEngine;
    }

    public function getFailureDetector(): SwimFailureDetector
    {
        return $this->failureDetector;
    }

    public function getDisseminator(): GossipDisseminator
    {
        return $this->disseminator;
    }

    public function getDnsResolver(): DnsResolver
    {
        return $this->dnsResolver;
    }

    public function getDnsParser(): DnsPacketParser
    {
        return $this->dnsParser;
    }

    public function getLocalNode(): GossipNode
    {
        return $this->localNode;
    }

    /**
     * Register a new service instance with optional health checks.
     *
     * @param array<string> $tags
     * @param array<string, string> $meta
     * @param array<string, mixed> $checkConfig
     */
    public function registerService(
        string $name,
        string $address,
        int $port,
        string $id = '',
        array $tags = [],
        array $meta = [],
        array $checkConfig = []
    ): ServiceInstance {
        $serviceId = $id !== '' ? $id : ($name . '-' . $port . '-' . substr(md5(random_bytes(6)), 0, 6));

        $checks = [];
        if (!empty($checkConfig)) {
            $checkType = CheckType::tryFrom((string)($checkConfig['type'] ?? 'ttl')) ?? CheckType::TTL;
            $check = new ServiceCheck(
                $checkConfig['id'] ?? ($serviceId . '-check'),
                $checkConfig['name'] ?? ($name . ' health check'),
                $checkType,
                CheckStatus::PASSING,
                $checkConfig
            );
            $checks[] = $check;
        }

        $instance = new ServiceInstance(
            $serviceId,
            $name,
            $address,
            $port,
            $tags,
            $meta,
            ['passing' => 1, 'warning' => 1],
            $checks
        );

        $this->catalog->registerService($instance);
        return $instance;
    }

    /**
     * Deregister a service instance.
     */
    public function deregisterService(string $instanceId): bool
    {
        return $this->catalog->deregisterService($instanceId);
    }

    /**
     * Discover healthy instances of a service.
     *
     * @param array<string> $tags
     * @return array<ServiceInstance>
     */
    public function discover(string $serviceName, array $tags = [], bool $healthyOnly = true): array
    {
        if (!$this->catalog->hasService($serviceName)) {
            return [];
        }

        if (!empty($tags)) {
            $instances = $this->catalog->filterByTags($serviceName, $tags);
            if ($healthyOnly) {
                return array_values(array_filter($instances, fn (ServiceInstance $i) => $i->isHealthy()));
            }
            return $instances;
        }

        return $healthyOnly
            ? $this->catalog->selectHealthy($serviceName)
            : array_values($this->catalog->getServiceInstances($serviceName));
    }

    /**
     * Resolve a service to a single instance using load balancing.
     */
    public function resolve(string $serviceName, string $strategy = 'round_robin'): ?ServiceInstance
    {
        if (!$this->catalog->hasService($serviceName)) {
            return null;
        }

        return $this->catalog->loadBalance($serviceName, $strategy);
    }

    /**
     * Join a peer node into the gossip cluster mesh.
     */
    public function join(string $address, int $port = 8301, string $nodeName = '', string $nodeId = ''): GossipNode
    {
        $id = $nodeId !== '' ? $nodeId : md5($address . ':' . $port);
        $name = $nodeName !== '' ? $nodeName : ('node-' . $id);

        $node = new GossipNode($id, $name, $address, $port, NodeStatus::ALIVE);
        $this->failureDetector->addMember($node);
        $this->disseminator->broadcast('join', $node);

        return $node;
    }

    /**
     * Return all active gossip cluster members.
     *
     * @return array<string, GossipNode>
     */
    public function members(bool $aliveOnly = false): array
    {
        return $aliveOnly
            ? $this->failureDetector->getAliveMembers()
            : $this->failureDetector->getMembers();
    }

    /**
     * Perform DNS resolution for a domain query string.
     *
     * @return array<array{name: string, type: DnsRecordType, ttl: int, data: mixed}>
     */
    public function dnsLookup(string $qname, string $type = 'ANY'): array
    {
        $qtype = DnsRecordType::fromName($type) ?? DnsRecordType::ANY;
        return $this->dnsResolver->resolveQuery($qname, $qtype);
    }

    /**
     * Process raw binary DNS UDP packet wire-format and return serialized response packet.
     */
    public function handleDnsPacket(string $rawPacket): string
    {
        $query = $this->dnsParser->parse($rawPacket);
        $response = $this->dnsResolver->resolve($query);
        return $this->dnsParser->serialize($response);
    }

    /**
     * Submit a TTL health check heartbeat.
     */
    public function passHealthCheck(string $instanceId, string $checkId, string $note = ''): bool
    {
        return $this->healthEngine->passTtl($instanceId, $checkId, $note);
    }

    /**
     * Run all health probes and evict dead services.
     *
     * @return array{checked: int, evicted: array<string>}
     */
    public function checkHealth(float $currentTime = 0.0): array
    {
        return $this->healthEngine->runAllChecks($currentTime);
    }
}
