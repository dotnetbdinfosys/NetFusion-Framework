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

namespace NetFusion\Core\Kube;

use InvalidArgumentException;
use NetFusion\Core\Kube\Autoscaler\HorizontalPodAutoscaler;
use NetFusion\Core\Kube\Controller\DeploymentController;
use NetFusion\Core\Kube\Controller\PodLifecycleController;
use NetFusion\Core\Kube\Models\Deployment;
use NetFusion\Core\Kube\Models\Node;
use NetFusion\Core\Kube\Models\Pod;
use NetFusion\Core\Kube\Models\Service;
use NetFusion\Core\Kube\Network\IpamManager;
use NetFusion\Core\Kube\Network\NetworkPolicyEngine;
use NetFusion\Core\Kube\Network\ServiceClusterProxy;
use NetFusion\Core\Kube\Parser\ManifestParser;
use NetFusion\Core\Kube\Scheduler\PodScheduler;
use NetFusion\Core\Kube\Storage\VolumeAttachmentManager;

/**
 * Master Enterprise Kubernetes Orchestrator Facade for NetFusion Framework.
 */
class KubeManager
{
    protected static ?self $instance = null;

    /** @var array<string, Node> Registered nodes keyed by name */
    protected array $nodes = [];

    /** @var array<string, Pod> Pods keyed by "namespace/name" */
    protected array $pods = [];

    /** @var array<string, Deployment> Deployments keyed by "namespace/name" */
    protected array $deployments = [];

    /** @var array<string, Service> Services keyed by "namespace/name" */
    protected array $services = [];

    /** @var array<string, HorizontalPodAutoscaler> HPAs keyed by deployment name */
    protected array $hpas = [];

    protected ManifestParser $parser;
    protected PodScheduler $scheduler;
    protected PodLifecycleController $lifecycle;
    protected IpamManager $ipam;
    protected ServiceClusterProxy $proxy;
    protected VolumeAttachmentManager $volumes;
    protected NetworkPolicyEngine $networkPolicy;
    protected DeploymentController $deploymentController;

    public function __construct(
        ?ManifestParser $parser = null,
        ?PodScheduler $scheduler = null,
        ?PodLifecycleController $lifecycle = null,
        ?IpamManager $ipam = null,
        ?ServiceClusterProxy $proxy = null,
        ?VolumeAttachmentManager $volumes = null,
        ?NetworkPolicyEngine $networkPolicy = null
    ) {
        $this->parser = $parser ?? new ManifestParser();
        $this->scheduler = $scheduler ?? new PodScheduler();
        $this->lifecycle = $lifecycle ?? new PodLifecycleController();
        $this->ipam = $ipam ?? new IpamManager();
        $this->proxy = $proxy ?? new ServiceClusterProxy();
        $this->volumes = $volumes ?? new VolumeAttachmentManager();
        $this->networkPolicy = $networkPolicy ?? new NetworkPolicyEngine();

        $this->deploymentController = new DeploymentController(
            $this->scheduler,
            $this->lifecycle,
            $this->ipam,
            $this->parser
        );

        // Register default simulated worker nodes if empty
        if (empty($this->nodes)) {
            $this->registerDefaultNodes();
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Bootstrap default simulated cluster worker nodes.
     */
    protected function registerDefaultNodes(): void
    {
        $this->registerNode(new Node(
            name: 'node-worker-1',
            internalIp: '192.168.1.101',
            status: 'Ready',
            labels: ['kubernetes.io/hostname' => 'node-worker-1', 'node-role.kubernetes.io/worker' => '']
        ));
        $this->registerNode(new Node(
            name: 'node-worker-2',
            internalIp: '192.168.1.102',
            status: 'Ready',
            labels: ['kubernetes.io/hostname' => 'node-worker-2', 'node-role.kubernetes.io/worker' => '']
        ));
    }

    public function registerNode(Node $node): self
    {
        $this->nodes[$node->getName()] = $node;
        return $this;
    }

    /**
     * @return array<string, Node>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function getNode(string $name): ?Node
    {
        return $this->nodes[$name] ?? null;
    }

    /**
     * Apply declarative Kubernetes YAML or JSON manifest string.
     *
     * @param string $manifestContent
     * @return object[] Array of created/updated resource objects
     */
    public function apply(string $manifestContent): array
    {
        $resources = $this->parser->parse($manifestContent);
        $applied = [];

        foreach ($resources as $resource) {
            if ($resource instanceof Node) {
                $this->registerNode($resource);
                $applied[] = $resource;
            } elseif ($resource instanceof Pod) {
                $this->scheduleAndRunPod($resource);
                $key = "{$resource->getNamespace()}/{$resource->getName()}";
                $this->pods[$key] = $resource;
                $applied[] = $resource;
            } elseif ($resource instanceof Deployment) {
                $key = "{$resource->getNamespace()}/{$resource->getName()}";
                $this->deployments[$key] = $resource;
                $this->reconcileDeployment($resource);
                $applied[] = $resource;
            } elseif ($resource instanceof Service) {
                $key = "{$resource->getNamespace()}/{$resource->getName()}";
                $this->proxy->registerService($resource);
                $this->services[$key] = $resource;
                $this->syncServiceEndpoints($resource);
                $applied[] = $resource;
            }
        }

        return $applied;
    }

    /**
     * Schedule a pod, assign IP, attach volumes and transition to Running.
     */
    public function scheduleAndRunPod(Pod $pod): Node
    {
        $node = $this->scheduler->schedule($pod, array_values($this->nodes));
        $this->ipam->allocateIp($pod);
        $this->volumes->mountPodVolumes($pod);
        $this->lifecycle->startPod($pod);
        return $node;
    }

    /**
     * Reconcile a specific deployment.
     */
    public function reconcileDeployment(Deployment $deployment): void
    {
        $podList = array_values($this->pods);
        $result = $this->deploymentController->reconcile($deployment, $podList, array_values($this->nodes));

        // Rebuild pod map from reconciled pod list
        $this->pods = [];
        foreach ($podList as $p) {
            $key = "{$p->getNamespace()}/{$p->getName()}";
            $this->pods[$key] = $p;
        }

        // Sync service endpoints matching any updated pods
        foreach ($this->services as $service) {
            $this->syncServiceEndpoints($service);
        }
    }

    /**
     * Sync endpoints for a Service against all current pods.
     */
    public function syncServiceEndpoints(Service $service): void
    {
        $this->proxy->syncEndpoints($service, array_values($this->pods));
    }

    /**
     * Global reconciliation cycle: runs lifecycle probes, deployment reconciliations, and HPA.
     */
    public function reconcile(): void
    {
        // 1. Probes on all active pods
        foreach ($this->pods as $pod) {
            $this->lifecycle->reconcilePod($pod);
        }

        // 2. Reconcile all deployments
        foreach ($this->deployments as $deployment) {
            $this->reconcileDeployment($deployment);
        }

        // 3. Reconcile services
        foreach ($this->services as $service) {
            $this->syncServiceEndpoints($service);
        }
    }

    /**
     * Scale a named deployment.
     */
    public function scaleDeployment(string $name, int $replicas, string $namespace = 'default'): Deployment
    {
        $key = "{$namespace}/{$name}";
        $deployment = $this->deployments[$key] ?? null;

        if ($deployment === null) {
            throw new InvalidArgumentException("Deployment '{$name}' not found in namespace '{$namespace}'.");
        }

        $deployment->setReplicas($replicas);
        $this->reconcileDeployment($deployment);

        return $deployment;
    }

    /**
     * Register a HorizontalPodAutoscaler.
     */
    public function registerHpa(HorizontalPodAutoscaler $hpa): self
    {
        $this->hpas[$hpa->getDeploymentName()] = $hpa;
        return $this;
    }

    /**
     * Evaluate HPA for a deployment with an observed metric value.
     */
    public function autoscale(string $deploymentName, float $currentUtilization, string $namespace = 'default'): int
    {
        $hpa = $this->hpas[$deploymentName] ?? null;
        if ($hpa === null) {
            throw new InvalidArgumentException("No HPA configured for deployment '{$deploymentName}'.");
        }

        $deployment = $this->getDeployment($deploymentName, $namespace);
        if ($deployment === null) {
            throw new InvalidArgumentException("Deployment '{$deploymentName}' not found in namespace '{$namespace}'.");
        }

        $newReplicas = $hpa->evaluate($deployment, $currentUtilization, true);
        $this->reconcileDeployment($deployment);

        return $newReplicas;
    }

    /**
     * Route a request targeting a Service ClusterIP and port.
     *
     * @return array{target_ip: string, target_port: int, service_name: string}
     */
    public function routeService(string $clusterIp, int $port): array
    {
        return $this->proxy->route($clusterIp, $port);
    }

    /**
     * @return Pod[]
     */
    public function getPods(?string $namespace = null): array
    {
        if ($namespace === null) {
            return array_values($this->pods);
        }

        return array_values(array_filter(
            $this->pods,
            fn(Pod $p) => $p->getNamespace() === $namespace
        ));
    }

    public function getPod(string $name, string $namespace = 'default'): ?Pod
    {
        return $this->pods["{$namespace}/{$name}"] ?? null;
    }

    /**
     * @return Deployment[]
     */
    public function getDeployments(?string $namespace = null): array
    {
        if ($namespace === null) {
            return array_values($this->deployments);
        }

        return array_values(array_filter(
            $this->deployments,
            fn(Deployment $d) => $d->getNamespace() === $namespace
        ));
    }

    public function getDeployment(string $name, string $namespace = 'default'): ?Deployment
    {
        return $this->deployments["{$namespace}/{$name}"] ?? null;
    }

    /**
     * @return Service[]
     */
    public function getServices(?string $namespace = null): array
    {
        if ($namespace === null) {
            return array_values($this->services);
        }

        return array_values(array_filter(
            $this->services,
            fn(Service $s) => $s->getNamespace() === $namespace
        ));
    }

    public function getService(string $name, string $namespace = 'default'): ?Service
    {
        return $this->services["{$namespace}/{$name}"] ?? null;
    }

    public function getScheduler(): PodScheduler
    {
        return $this->scheduler;
    }

    public function getLifecycle(): PodLifecycleController
    {
        return $this->lifecycle;
    }

    public function getIpam(): IpamManager
    {
        return $this->ipam;
    }

    public function getProxy(): ServiceClusterProxy
    {
        return $this->proxy;
    }

    public function getVolumes(): VolumeAttachmentManager
    {
        return $this->volumes;
    }

    public function getNetworkPolicy(): NetworkPolicyEngine
    {
        return $this->networkPolicy;
    }

    /**
     * Reset cluster state.
     */
    public function reset(): void
    {
        $this->nodes = [];
        $this->pods = [];
        $this->deployments = [];
        $this->services = [];
        $this->hpas = [];
        $this->registerDefaultNodes();
    }
}
