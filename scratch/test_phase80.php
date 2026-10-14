<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - Phase 80 Test Suite
 * |--------------------------------------------------------------------------
 * |
 * | Enterprise Kubernetes-Compatible Container Orchestration & Pod Lifecycle
 * | Controller, CNI/CSI Fabric, Declarative Manifest & Cluster Autoscaler
 * |
 * |--------------------------------------------------------------------------
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';


use NetFusion\Core\Kube\Autoscaler\HorizontalPodAutoscaler;
use NetFusion\Core\Kube\Controller\DeploymentController;
use NetFusion\Core\Kube\Controller\PodLifecycleController;
use NetFusion\Core\Kube\Controller\ProbeExecutor;
use NetFusion\Core\Kube\Enums\ContainerState;
use NetFusion\Core\Kube\Enums\PodPhase;
use NetFusion\Core\Kube\Enums\ProbeType;
use NetFusion\Core\Kube\Enums\RestartPolicy;
use NetFusion\Core\Kube\Enums\ServiceType;
use NetFusion\Core\Kube\Enums\TaintEffect;
use NetFusion\Core\Kube\Exceptions\SchedulerException;
use NetFusion\Core\Kube\KubeManager;
use NetFusion\Core\Kube\Models\ContainerSpec;
use NetFusion\Core\Kube\Models\Deployment;
use NetFusion\Core\Kube\Models\Node;
use NetFusion\Core\Kube\Models\Pod;
use NetFusion\Core\Kube\Models\ProbeConfig;
use NetFusion\Core\Kube\Models\ResourceRequirements;
use NetFusion\Core\Kube\Models\Service;
use NetFusion\Core\Kube\Models\Taint;
use NetFusion\Core\Kube\Models\Toleration;
use NetFusion\Core\Kube\Models\VolumeMount;
use NetFusion\Core\Kube\Network\IpamManager;
use NetFusion\Core\Kube\Network\NetworkPolicyEngine;
use NetFusion\Core\Kube\Network\ServiceClusterProxy;
use NetFusion\Core\Kube\Parser\ManifestParser;
use NetFusion\Core\Kube\Scheduler\Filters\AffinityFilter;
use NetFusion\Core\Kube\Scheduler\Filters\CapacityFilter;
use NetFusion\Core\Kube\Scheduler\Filters\TaintTolerationFilter;
use NetFusion\Core\Kube\Scheduler\PodScheduler;
use NetFusion\Core\Kube\Scheduler\PriorityScorer;
use NetFusion\Core\Kube\Storage\Drivers\MemoryTmpfsDriver;
use NetFusion\Core\Kube\Storage\VolumeAttachmentManager;

// Bootstrap application container
$app = new \NetFusion\Core\Application(dirname(__DIR__));

$passed = 0;
$failed = 0;


function it(string $description, callable $fn): void {
    global $passed, $failed;
    try {
        $fn();
        echo "  ✔ [P80] {$description}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  ✗ [P80] {$description}\n";
        echo "     Error: {$e->getMessage()}\n";
        echo "     File: {$e->getFile()}:{$e->getLine()}\n";
        $failed++;
    }
}


echo "\n--- Phase 80: Enterprise Kubernetes Container Orchestration & Pod Fabric Suite ---\n\n";

// 1. ResourceRequirements parsing and arithmetic
it('ResourceRequirements parses strings and performs capacity calculations', function () {
    $res = ResourceRequirements::parse('500m', '256Mi');
    if ($res->getCpuMillis() !== 500) {
        throw new Exception("Expected 500m CPU, got {$res->getCpuMillis()}");
    }
    if ($res->getMemoryMib() !== 256) {
        throw new Exception("Expected 256Mi RAM, got {$res->getMemoryMib()}");
    }

    $nodeRes = ResourceRequirements::parse('2', '1Gi'); // 2000m, 1024Mi
    if (!$nodeRes->fits($res)) {
        throw new Exception("Node with 2 CPUs and 1Gi RAM should fit 500m and 256Mi");
    }

    $remaining = $nodeRes->subtract($res);
    if ($remaining->getCpuMillis() !== 1500 || $remaining->getMemoryMib() !== 768) {
        throw new Exception("Expected 1500m and 768Mi remaining");
    }
});

// 2. ManifestParser parsing multi-document YAML
it('ManifestParser parses multi-document declarative YAML into typed objects', function () {
    $yaml = <<<YAML
apiVersion: v1
kind: Pod
metadata:
  name: web-app
  namespace: prod
  labels:
    app: web
    tier: frontend
spec:
  restartPolicy: Always
  nodeSelector:
    disktype: ssd
  containers:
    - name: nginx
      image: nginx:1.25
      ports:
        - containerPort: 80
      resources:
        requests:
          cpu: 200m
          memory: 128Mi
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: api-deploy
  namespace: prod
spec:
  replicas: 3
  selector:
    matchLabels:
      app: api
  template:
    metadata:
      labels:
        app: api
    spec:
      containers:
        - name: php-api
          image: php:8.2-fpm
---
apiVersion: v1
kind: Service
metadata:
  name: web-svc
  namespace: prod
spec:
  type: ClusterIP
  selector:
    app: web
  ports:
    - port: 80
      targetPort: 8080
      protocol: TCP
YAML;

    $parser = new ManifestParser();
    $resources = $parser->parse($yaml);

    if (count($resources) !== 3) {
        throw new Exception("Expected 3 parsed resources, got " . count($resources));
    }

    [$pod, $deploy, $svc] = $resources;

    if (!($pod instanceof Pod) || $pod->getName() !== 'web-app' || $pod->getNamespace() !== 'prod') {
        throw new Exception("Pod parsing failed");
    }
    if ($pod->getLabel('tier') !== 'frontend' || $pod->getNodeSelector()['disktype'] !== 'ssd') {
        throw new Exception("Pod labels or nodeSelector mismatch");
    }
    if ($pod->getTotalResources()->getCpuMillis() !== 200) {
        throw new Exception("Pod container total resources mismatch");
    }

    if (!($deploy instanceof Deployment) || $deploy->getName() !== 'api-deploy' || $deploy->getReplicas() !== 3) {
        throw new Exception("Deployment parsing failed");
    }

    if (!($svc instanceof Service) || $svc->getName() !== 'web-svc' || $svc->getType() !== ServiceType::CLUSTER_IP) {
        throw new Exception("Service parsing failed");
    }
    if ($svc->getPorts()[0]['targetPort'] !== 8080) {
        throw new Exception("Service port parsing mismatch");
    }
});

// 3. PodLifecycleController startup, readiness, and liveness probes
it('PodLifecycleController manages startup, readiness, and restarts on liveness failure', function () {
    $probeExecutor = new ProbeExecutor();
    $controller = new PodLifecycleController($probeExecutor);

    $container = new ContainerSpec(
        name: 'app',
        image: 'my-app:1.0',
        livenessProbe: new ProbeConfig(
            type: ProbeType::LIVENESS,
            mechanism: 'httpGet',
            spec: ['path' => '/healthz', 'port' => 8080],
            failureThreshold: 2
        ),
        readinessProbe: new ProbeConfig(
            type: ProbeType::READINESS,
            mechanism: 'httpGet',
            spec: ['path' => '/ready', 'port' => 8080],
            successThreshold: 1,
            failureThreshold: 1
        )
    );

    $pod = new Pod(name: 'demo-pod', containers: [$container]);
    if ($pod->getPhase() !== PodPhase::PENDING) {
        throw new Exception("Pod should start in Pending phase");
    }

    // Start pod
    $controller->startPod($pod);
    if ($pod->getPhase() !== PodPhase::RUNNING) {
        throw new Exception("Pod should transition to Running phase");
    }
    if ($pod->isReady()) {
        throw new Exception("Pod should not be ready before readiness probe succeeds");
    }

    // Mock probes: ready succeeds, liveness succeeds
    $probeExecutor->registerHandler('app:readiness', fn() => true);
    $probeExecutor->registerHandler('app:liveness', fn() => true);

    $controller->reconcilePod($pod);
    if (!$pod->isReady()) {
        throw new Exception("Pod should be ready after readiness probe succeeds");
    }

    // Now simulate liveness failure
    $probeExecutor->registerHandler('app:liveness', fn() => false);
    $controller->reconcilePod($pod); // failure 1
    $status = $pod->getContainerStatus('app');
    if ($status->getRestartCount() !== 0) {
        throw new Exception("Container should not restart on first failure (threshold = 2)");
    }

    $controller->reconcilePod($pod); // failure 2 -> triggers restart
    if ($status->getRestartCount() !== 1) {
        throw new Exception("Container should have restarted once (restartCount: {$status->getRestartCount()})");
    }

    // Gracefully terminate pod
    $controller->terminatePod($pod, 0);
    if ($pod->getPhase() !== PodPhase::SUCCEEDED || !$pod->isTerminal()) {
        throw new Exception("Pod should transition to Succeeded phase");
    }
});

// 4. PodScheduler filtering predicates (Capacity, Affinity, Taints)
it('PodScheduler filters nodes by capacity, nodeSelector, and taints', function () {
    $node1 = new Node(
        name: 'node-small',
        capacity: new ResourceRequirements(1000, 1024),
        allocatable: new ResourceRequirements(1000, 1024),
        labels: ['disktype' => 'hdd']
    );

    $node2 = new Node(
        name: 'node-ssd',
        capacity: new ResourceRequirements(4000, 8192),
        allocatable: new ResourceRequirements(4000, 8192),
        labels: ['disktype' => 'ssd']
    );

    $node3 = new Node(
        name: 'node-gpu',
        capacity: new ResourceRequirements(8000, 16384),
        allocatable: new ResourceRequirements(8000, 16384),
        labels: ['disktype' => 'ssd'],
        taints: [new Taint('dedicated', 'gpu', TaintEffect::NO_SCHEDULE)]
    );

    $nodes = [$node1, $node2, $node3];
    $scheduler = new PodScheduler();

    // Case A: Pod requires 2000m CPU (node1 too small, node2 & node3 fit) and disktype=ssd
    $podA = new Pod(
        name: 'heavy-pod',
        containers: [new ContainerSpec('app', 'app:latest', resources: new ResourceRequirements(2000, 2048))],
        nodeSelector: ['disktype' => 'ssd']
    );

    // Node3 has GPU taint without toleration, so only node2 qualifies
    $selected = $scheduler->schedule($podA, $nodes);
    if ($selected->getName() !== 'node-ssd') {
        throw new Exception("Expected node-ssd to be selected, got {$selected->getName()}");
    }
    if ($podA->getNodeName() !== 'node-ssd') {
        throw new Exception("Pod nodeName was not bound to node-ssd");
    }

    // Case B: Pod with GPU toleration
    $podB = new Pod(
        name: 'gpu-pod',
        containers: [new ContainerSpec('gpu-job', 'cuda:latest', resources: new ResourceRequirements(1000, 2048))],
        nodeSelector: ['disktype' => 'ssd'],
        tolerations: [new Toleration('dedicated', 'Equal', 'gpu', TaintEffect::NO_SCHEDULE)]
    );

    $selectedB = $scheduler->schedule($podB, $nodes);
    // Node3 has more free resources (8000m vs node2's now 2000m left) -> LeastAllocated chooses node3
    if ($selectedB->getName() !== 'node-gpu') {
        throw new Exception("Expected node-gpu to be selected for pod with toleration, got {$selectedB->getName()}");
    }

    // Case C: Pod exceeding all nodes capacity
    $hugePod = new Pod(
        name: 'huge-pod',
        containers: [new ContainerSpec('titan', 'img', resources: new ResourceRequirements(50000, 65536))]
    );
    try {
        $scheduler->schedule($hugePod, $nodes);
        throw new Exception("Scheduler should have thrown exception for unfulfillable capacity");
    } catch (SchedulerException $e) {
        // Expected
    }
});

// 5. IpamManager IP address allocation & deallocation
it('IpamManager allocates and releases unique virtual IPs in CNI subnet', function () {
    $ipam = new IpamManager('10.244', 0);
    $pod1 = new Pod(name: 'pod-1');
    $pod2 = new Pod(name: 'pod-2');

    $ip1 = $ipam->allocateIp($pod1);
    $ip2 = $ipam->allocateIp($pod2);

    if ($ip1 === $ip2) {
        throw new Exception("Allocated duplicate IPs: {$ip1}");
    }
    if ($pod1->getIp() !== $ip1 || $pod2->getIp() !== $ip2) {
        throw new Exception("Pod IP was not updated on model");
    }
    if (!str_starts_with($ip1, '10.244.0.')) {
        throw new Exception("IP not in expected subnet: {$ip1}");
    }
    if ($ipam->getAllocatedCount() !== 2) {
        throw new Exception("Expected 2 allocated IPs");
    }

    $ipam->releaseIp($pod1);
    if ($pod1->getIp() !== null || $ipam->isAllocated($ip1)) {
        throw new Exception("IP was not cleanly released");
    }
    if ($ipam->getAllocatedCount() !== 1) {
        throw new Exception("Expected 1 allocated IP remaining");
    }
});

// 6. NetworkPolicyEngine ingress firewall rules
it('NetworkPolicyEngine evaluates pod isolation and ingress rules', function () {
    $engine = new NetworkPolicyEngine();

    $frontendPod = new Pod(name: 'fe-pod', labels: ['app' => 'frontend']);
    $dbPod = new Pod(name: 'db-pod', labels: ['app' => 'database']);
    $unknownPod = new Pod(name: 'rogue-pod', labels: ['app' => 'rogue']);

    // Default: no policies -> all traffic allowed
    if (!$engine->isIngressAllowed($unknownPod, $dbPod, 5432)) {
        throw new Exception("Default unisolated traffic should be allowed");
    }

    // Apply policy: isolate 'app: database', allow only 'app: frontend' on port 5432
    $engine->addPolicy('db-ingress-policy', [
        'podSelector' => ['matchLabels' => ['app' => 'database']],
        'ingress'     => [
            [
                'from'  => [
                    ['podSelector' => ['matchLabels' => ['app' => 'frontend']]],
                ],
                'ports' => [
                    ['port' => 5432, 'protocol' => 'TCP'],
                ],
            ],
        ],
    ]);

    // Frontend on port 5432 should be allowed
    if (!$engine->isIngressAllowed($frontendPod, $dbPod, 5432)) {
        throw new Exception("Frontend pod should be permitted to connect to database on port 5432");
    }

    // Frontend on port 8080 should be blocked
    if ($engine->isIngressAllowed($frontendPod, $dbPod, 8080)) {
        throw new Exception("Frontend pod should be denied on non-whitelisted port 8080");
    }

    // Rogue pod should be blocked
    if ($engine->isIngressAllowed($unknownPod, $dbPod, 5432)) {
        throw new Exception("Rogue pod should be blocked by network policy");
    }
});

// 7. ServiceClusterProxy ClusterIP routing & load balancing
it('ServiceClusterProxy assigns ClusterIP and balances requests across endpoints', function () {
    $proxy = new ServiceClusterProxy('10.96.0');
    $service = new Service(
        name: 'web-svc',
        ports: [['port' => 80, 'targetPort' => 8080, 'protocol' => 'TCP']],
        selector: ['app' => 'web']
    );

    $clusterIp = $proxy->registerService($service);
    if (!str_starts_with($clusterIp, '10.96.0.')) {
        throw new Exception("Invalid ClusterIP generated: {$clusterIp}");
    }

    // Create 2 ready pods matching selector
    $pod1 = new Pod(name: 'web-1', labels: ['app' => 'web'], phase: PodPhase::RUNNING, ip: '10.244.0.15');
    $pod1->getContainerStatus('web-1')?->setReady(true);
    // Ensure isReady() is true by setting status
    $cs1 = new \NetFusion\Core\Kube\Models\ContainerStatus('web', ContainerState::RUNNING, true);
    $pod1 = new Pod(name: 'web-1', labels: ['app' => 'web'], phase: PodPhase::RUNNING, ip: '10.244.0.15', containerStatuses: ['web' => $cs1]);

    $cs2 = new \NetFusion\Core\Kube\Models\ContainerStatus('web', ContainerState::RUNNING, true);
    $pod2 = new Pod(name: 'web-2', labels: ['app' => 'web'], phase: PodPhase::RUNNING, ip: '10.244.0.16', containerStatuses: ['web' => $cs2]);

    $proxy->syncEndpoints($service, [$pod1, $pod2]);
    if (count($service->getEndpoints()) !== 2) {
        throw new Exception("Expected 2 synced endpoints, got " . count($service->getEndpoints()));
    }

    // Route requests
    $route1 = $proxy->route($clusterIp, 80);
    $route2 = $proxy->route($clusterIp, 80);

    if ($route1['target_port'] !== 8080 || $route2['target_port'] !== 8080) {
        throw new Exception("Expected target_port 8080");
    }
    // Round-robin should alternate between the 2 pod IPs
    if ($route1['target_ip'] === $route2['target_ip']) {
        throw new Exception("Expected round-robin load balancing across different pod IPs");
    }
});

// 8. Storage VolumeAttachmentManager & MemoryTmpfsDriver
it('VolumeAttachmentManager mounts ephemeral tmpfs storage for Pods', function () {
    $mgr = new VolumeAttachmentManager();
    $driver = new MemoryTmpfsDriver('cache-vol');
    $mgr->registerVolume($driver);

    $mount = new VolumeMount('cache-vol', '/var/cache');
    $container = new ContainerSpec('app', 'app:1.0', volumeMounts: [$mount]);
    $pod = new Pod(name: 'storage-pod', containers: [$container]);

    $mgr->mountPodVolumes($pod);

    $driver->write('/var/cache/session.txt', 'SESSION_TOKEN_123');
    if (!$driver->exists('/var/cache/session.txt')) {
        throw new Exception("File should exist in mounted volume");
    }
    if ($driver->read('/var/cache/session.txt') !== 'SESSION_TOKEN_123') {
        throw new Exception("File content mismatch in mounted volume");
    }

    $mgr->unmountPodVolumes($pod);
});

// 9. DeploymentController scaling up and scaling down
it('DeploymentController scales replicas up and down reconciles cluster state', function () {
    $scheduler = new PodScheduler();
    $lifecycle = new PodLifecycleController();
    $ipam = new IpamManager();
    $controller = new DeploymentController($scheduler, $lifecycle, $ipam);

    $node = new Node('worker-1', capacity: new ResourceRequirements(10000, 20480), allocatable: new ResourceRequirements(10000, 20480));
    $nodes = [$node];

    $deployment = new Deployment(
        name: 'nginx-deploy',
        replicas: 2,
        matchLabels: ['app' => 'nginx'],
        template: [
            'metadata' => ['labels' => ['app' => 'nginx']],
            'spec'     => [
                'containers' => [
                    ['name' => 'nginx', 'image' => 'nginx:alpine', 'resources' => ['requests' => ['cpu' => '100m', 'memory' => '64Mi']]],
                ],
            ],
        ]
    );

    $clusterPods = [];

    // Initial reconcile: should create 2 pods
    $result1 = $controller->reconcile($deployment, $clusterPods, $nodes);
    if (count($result1['created']) !== 2 || count($clusterPods) !== 2) {
        throw new Exception("Expected 2 pods created on initial scale up");
    }
    if ($deployment->getAvailableReplicas() !== 2) {
        throw new Exception("Expected 2 available replicas on deployment");
    }

    // Scale up to 4
    $deployment->setReplicas(4);
    $result2 = $controller->reconcile($deployment, $clusterPods, $nodes);
    if (count($result2['created']) !== 2 || count($clusterPods) !== 4) {
        throw new Exception("Expected 2 additional pods created to reach replica count 4");
    }

    // Scale down to 1
    $deployment->setReplicas(1);
    $result3 = $controller->reconcile($deployment, $clusterPods, $nodes);
    if (count($result3['terminated']) !== 3 || count($clusterPods) !== 1) {
        throw new Exception("Expected 3 pods terminated on scale down to 1");
    }
});

// 10. HorizontalPodAutoscaler (HPA) algorithm
it('HorizontalPodAutoscaler computes desired replicas from utilization metrics', function () {
    $hpa = new HorizontalPodAutoscaler(
        deploymentName: 'worker-deploy',
        minReplicas: 2,
        maxReplicas: 8,
        targetUtilization: 50.0,
        cooldownSeconds: 0
    );

    $deployment = new Deployment(name: 'worker-deploy', replicas: 2);

    // Metric is at 100% (double target of 50%) -> should scale to 4
    $desired = $hpa->evaluate($deployment, 100.0, true);
    if ($desired !== 4 || $deployment->getReplicas() !== 4) {
        throw new Exception("Expected scale up to 4 replicas (utilization 100% vs target 50%), got {$desired}");
    }

    // Metric at 200% -> 4 * (200 / 50) = 16, clamped to maxReplicas 8
    $desiredMax = $hpa->evaluate($deployment, 200.0, true);
    if ($desiredMax !== 8 || $deployment->getReplicas() !== 8) {
        throw new Exception("Expected clamped to maxReplicas 8, got {$desiredMax}");
    }

    // Metric at 10% -> 8 * (10 / 50) = 1.6 -> ceil 2, clamped to minReplicas 2
    $desiredMin = $hpa->evaluate($deployment, 10.0, true);
    if ($desiredMin !== 2 || $deployment->getReplicas() !== 2) {
        throw new Exception("Expected scale down to minReplicas 2, got {$desiredMin}");
    }
});

// 11. KubeManager unified facade & global helpers
it('KubeManager facade applies YAML manifest and manages cluster lifecycle', function () {
    $kube = kube();
    $kube->reset();

    $yaml = <<<YAML
apiVersion: apps/v1
kind: Deployment
metadata:
  name: shop-backend
  namespace: default
spec:
  replicas: 2
  selector:
    matchLabels:
      app: shop-backend
  template:
    metadata:
      labels:
        app: shop-backend
    spec:
      containers:
        - name: backend
          image: shop:v2
          ports:
            - containerPort: 9000
---
apiVersion: v1
kind: Service
metadata:
  name: shop-backend-svc
  namespace: default
spec:
  type: ClusterIP
  selector:
    app: shop-backend
  ports:
    - port: 9000
      targetPort: 9000
YAML;

    $applied = kube_apply($yaml);
    if (count($applied) !== 2) {
        throw new Exception("Expected 2 resources applied from manifest");
    }

    $pods = kube_pods('default');
    if (count($pods) !== 2) {
        throw new Exception("Expected 2 active pods created for deployment, got " . count($pods));
    }

    // Scale via helper
    $scaled = kube_scale('shop-backend', 3, 'default');
    if ($scaled->getReplicas() !== 3) {
        throw new Exception("Expected 3 replicas after kube_scale");
    }

    $podsAfterScale = kube_pods('default');
    if (count($podsAfterScale) !== 3) {
        throw new Exception("Expected 3 pods after scaling, got " . count($podsAfterScale));
    }

    // Test service resolution
    $svc = $kube->getService('shop-backend-svc');
    if ($svc === null || $svc->getClusterIp() === null) {
        throw new Exception("Service or ClusterIP was not registered");
    }

    $route = $kube->routeService($svc->getClusterIp(), 9000);
    if ($route['service_name'] !== 'shop-backend-svc' || !str_starts_with($route['target_ip'], '10.244.')) {
        throw new Exception("Service routing failed: " . json_encode($route));
    }
});

// 12. Database migration test
it('Database migration creates and drops Kubernetes tables', function () {
    $dbFile = sys_get_temp_dir() . '/test_kube_' . uniqid() . '.sqlite';
    $pdo = new PDO("sqlite:{$dbFile}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $conn = new class($pdo) extends \NetFusion\Core\Database\Connection {
        public function __construct(protected \PDO $pdoInstance) {}
        public function getPdo(): \PDO { return $this->pdoInstance; }
    };

    $migration = require __DIR__ . '/../database/migrations/2026_09_28_000063_create_kube_tables.php';

    // Up
    $migration->up($conn);
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'kube_%'")->fetchAll(PDO::FETCH_COLUMN);
    if (count($tables) < 5) {
        throw new Exception("Expected 5 kube tables created, found: " . implode(', ', $tables));
    }

    // Down
    $migration->down($conn);
    $tablesAfter = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'kube_%'")->fetchAll(PDO::FETCH_COLUMN);
    if (count($tablesAfter) !== 0) {
        throw new Exception("Tables not cleanly dropped by down()");
    }

    $conn = null;
    $pdo = null;
    @unlink($dbFile);
});

// 13. CLI commands execution
it('Kubernetes CLI commands execute cleanly', function () {
    global $app;
    $kube = kube();
    $kube->reset();


    // Seed deployment & pod
    $yaml = <<<YAML
apiVersion: apps/v1
kind: Deployment
metadata:
  name: web-cli
  namespace: default
spec:
  replicas: 2
  selector:
    matchLabels:
      app: web-cli
  template:
    metadata:
      labels:
        app: web-cli
    spec:
      containers:
        - name: web
          image: nginx:latest
          ports:
            - containerPort: 80
YAML;
    $kube->apply($yaml);

    // Test KubeGetCommand
    $getCmd = new \NetFusion\Core\Console\Commands\KubeGetCommand($app);
    ob_start();
    $exitGetNodes = $getCmd->handle(['nodes']);
    $exitGetPods = $getCmd->handle(['pods']);
    $exitGetDeploy = $getCmd->handle(['deployments']);
    $exitGetSvc = $getCmd->handle(['services']);
    $out = ob_get_clean();

    if ($exitGetNodes !== 0 || $exitGetPods !== 0 || $exitGetDeploy !== 0 || $exitGetSvc !== 0) {
        throw new Exception("KubeGetCommand failed with non-zero exit code");
    }

    // Test KubeScaleCommand
    $scaleCmd = new \NetFusion\Core\Console\Commands\KubeScaleCommand($app);
    ob_start();
    $exitScale = $scaleCmd->handle(['deployment', 'web-cli', '--replicas=4']);
    $scaleOut = ob_get_clean();

    if ($exitScale !== 0) {
        throw new Exception("KubeScaleCommand failed: {$scaleOut}");
    }
    if ($kube->getDeployment('web-cli')->getReplicas() !== 4) {
        throw new Exception("Deployment was not scaled to 4 by CLI command");
    }

    // Test KubeLogsCommand
    $pods = $kube->getPods('default');
    $firstPod = $pods[0]->getName();

    $logsCmd = new \NetFusion\Core\Console\Commands\KubeLogsCommand($app);
    ob_start();
    $exitLogs = $logsCmd->handle([$firstPod]);
    $logsOut = ob_get_clean();


    if ($exitLogs !== 0 || !str_contains($logsOut, 'nginx:latest')) {
        throw new Exception("KubeLogsCommand failed: {$logsOut}");
    }
});

// 14. Global helper functions
it('Global helpers kube(), kube_apply(), kube_pods(), kube_scale() operate as expected', function () {
    $manager = kube();
    if (!($manager instanceof \NetFusion\Core\Kube\KubeManager)) {
        throw new Exception("kube() did not return KubeManager instance");
    }

    $yaml = <<<YAML
apiVersion: apps/v1
kind: Deployment
metadata:
  name: helper-deploy
spec:
  replicas: 1
  selector:
    matchLabels:
      app: helper
  template:
    metadata:
      labels:
        app: helper
    spec:
      containers:
        - name: app
          image: busybox
YAML;

    $res = kube_apply($yaml);
    if (empty($res)) {
        throw new Exception("kube_apply() returned empty array");
    }

    $pods = kube_pods();
    if (empty($pods)) {
        throw new Exception("kube_pods() returned empty array");
    }

    $dep = kube_scale('helper-deploy', 2);
    if ($dep->getReplicas() !== 2) {
        throw new Exception("kube_scale() failed to scale deployment to 2");
    }
});



echo "\n--- Phase 80 Test Summary ---\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
$rate = ($passed + $failed) > 0 ? round(($passed / ($passed + $failed)) * 100, 2) : 0;
echo "Rate: {$rate}%\n";

if ($failed > 0) {
    exit(1);
}
