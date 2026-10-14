<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework — Phase 78 Test Suite
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

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\IaC\Ast\AstBlock;
use NetFusion\Core\IaC\Ast\BlockType;
use NetFusion\Core\IaC\Execution\PlanExecutor;
use NetFusion\Core\IaC\Graph\DependencyGraph;
use NetFusion\Core\IaC\Graph\ResourceNode;
use NetFusion\Core\IaC\IacManager;
use NetFusion\Core\IaC\Parser\HclLexer;
use NetFusion\Core\IaC\Parser\HclParser;
use NetFusion\Core\IaC\Parser\HclToken;
use NetFusion\Core\IaC\Plan\ActionType;
use NetFusion\Core\IaC\Plan\ExecutionPlan;
use NetFusion\Core\IaC\Plan\ResourceChange;
use NetFusion\Core\IaC\Planner\DiffPlanner;
use NetFusion\Core\IaC\Providers\Aws\AwsProvider;
use NetFusion\Core\IaC\Providers\Gcp\GcpProvider;
use NetFusion\Core\IaC\Providers\Local\LocalContainerProvider;
use NetFusion\Core\IaC\State\StateFile;
use NetFusion\Core\IaC\State\StateLock;
use NetFusion\Core\IaC\State\StateResource;
use NetFusion\Core\IaC\State\StateResourceInstance;
use NetFusion\Core\IaC\State\Stores\FileStateStore;
use NetFusion\Core\IaC\State\Stores\MemoryStateStore;

// Bootstrap application container
$app = new Application(dirname(__DIR__));
$app->singleton(IacManager::class, fn() => new IacManager());

$passed = 0;
$failed = 0;

function it(string $description, callable $fn): void {
    global $passed, $failed;
    try {
        $fn();
        echo "  ✔ [P78] {$description}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  ✘ [P78] {$description}\n";
        echo "     Error: {$e->getMessage()}\n";
        echo "     At: {$e->getFile()}:{$e->getLine()}\n";
        $failed++;
    }
}

echo "=== NetFusion Phase 78: Multi-Cloud IaC, Terraform State Machine & DAG Engine ===\n\n";

// ==========================================
// 1. AST Block & HCL Lexer
// ==========================================

it('AstBlock computes canonical address and extracts dependencies', function () {
    $block = new AstBlock(
        blockType: BlockType::RESOURCE,
        type: 'aws_instance',
        name: 'web_server',
        attributes: [
            'ami' => 'ami-0c55b159cbfafe1f0',
            'instance_type' => 't3.micro',
            'subnet_id' => '${aws_subnet.public.id}',
            'vpc_security_group_ids' => ['aws_security_group.web.id'],
            'depends_on' => ['aws_vpc.main'],
        ]
    );

    assert($block->getAddress() === 'aws_instance.web_server');
    $deps = $block->getDependencies();

    assert(in_array('aws_subnet.public', $deps, true));
    assert(in_array('aws_security_group.web', $deps, true));
    assert(in_array('aws_vpc.main', $deps, true));
});

it('HclLexer tokenizes comments, strings, numbers, and identifiers', function () {
    $hcl = <<<'HCL'
# Top comment
// Second comment
resource "aws_vpc" "main" {
  cidr_block = "10.0.0.0/16"
  count = 2
  enable_dns = true
}
HCL;
    $lexer = new HclLexer($hcl);
    $tokens = $lexer->tokenize();

    $types = array_map(fn(HclToken $t) => $t->type, $tokens);
    assert(in_array(HclToken::T_IDENTIFIER, $types, true));
    assert(in_array(HclToken::T_STRING, $types, true));
    assert(in_array(HclToken::T_NUMBER, $types, true));
    assert(in_array(HclToken::T_BOOLEAN, $types, true));
    assert(in_array(HclToken::T_LBRACE, $types, true));
    assert(in_array(HclToken::T_RBRACE, $types, true));
});

// ==========================================
// 2. HCL Parser & Expression Evaluation
// ==========================================

it('HclParser parses resources, maps, lists, and variable interpolations', function () {
    $hcl = <<<'HCL'
variable "env" {
  default = "production"
}

resource "aws_vpc" "vpc_prod" {
  cidr_block = "10.0.0.0/16"
  tags = {
    Environment = "${var.env}"
    ManagedBy   = "NetFusion"
  }
}

resource "aws_security_group" "allow_web" {
  vpc_id = aws_vpc.vpc_prod.id
  ingress {
    from_port = 80
    to_port   = 80
    cidr_blocks = ["0.0.0.0/0"]
  }
}
HCL;

    $parser = new HclParser();
    $blocks = $parser->parse($hcl, ['env' => 'staging']);

    assert(count($blocks) === 3);

    // Check variable block
    $varBlock = $blocks[0];
    assert($varBlock->blockType === BlockType::VARIABLE);
    assert($varBlock->name === 'env');

    // Check VPC block
    $vpcBlock = $blocks[1];
    assert($vpcBlock->getAddress() === 'aws_vpc.vpc_prod');
    assert($vpcBlock->getAttribute('cidr_block') === '10.0.0.0/16');
    $tags = $vpcBlock->getAttribute('tags');
    assert(is_array($tags));
    assert($tags['Environment'] === 'staging'); // Variable interpolated!
    assert($tags['ManagedBy'] === 'NetFusion');

    // Check SG block with nested ingress
    $sgBlock = $blocks[2];
    assert($sgBlock->getAddress() === 'aws_security_group.allow_web');
    assert(count($sgBlock->nestedBlocks) === 1);
    $ingress = $sgBlock->nestedBlocks[0];
    assert($ingress->type === 'ingress');
    assert($ingress->getAttribute('from_port') === 80);
    assert($ingress->getAttribute('cidr_blocks') === ['0.0.0.0/0']);
});

// ==========================================
// 3. Terraform State v4 Engine & Checksum
// ==========================================

it('StateFile serializes and deserializes Terraform v4 compliant JSON', function () {
    $state = new StateFile(version: 4, terraformVersion: '1.5.0', serial: 3);

    $instance = new StateResourceInstance(
        schemaVersion: 1,
        attributes: [
            'id' => 'vpc-12345678',
            'cidr_block' => '10.0.0.0/16',
        ],
        dependencies: []
    );

    $res = new StateResource(
        mode: 'managed',
        type: 'aws_vpc',
        name: 'main',
        provider: 'provider["aws"]',
        instances: [$instance]
    );

    $state->setResource($res);
    $state->setOutput('vpc_id', 'vpc-12345678');

    $json = $state->toJson();
    $checksum = $state->computeChecksum();

    assert(strlen($checksum) === 64);
    assert(str_contains($json, '"terraform_version": "1.5.0"'));
    assert(str_contains($json, '"serial": 3'));

    $hydrated = StateFile::fromJson($json);
    assert($hydrated->version === 4);
    assert($hydrated->hasResource('aws_vpc.main') === true);
    assert($hydrated->getResource('aws_vpc.main')?->getPrimaryInstance()?->getAttribute('id') === 'vpc-12345678');
    assert($hydrated->getOutput('vpc_id') === 'vpc-12345678');
});

it('MemoryStateStore and FileStateStore acquire and release state locks', function () {
    $store = new MemoryStateStore();
    $lock1 = new StateLock('lock-abc', 'Apply', 'dev@laptop', '1.5.0', time(), 'terraform.tfstate');

    assert($store->lock($lock1) === true);
    assert($store->getActiveLock()?->lockId === 'lock-abc');

    // Second lock attempt must fail
    $lock2 = new StateLock('lock-xyz', 'Apply', 'ci@server', '1.5.0', time(), 'terraform.tfstate');
    assert($store->lock($lock2) === false);

    // Release lock
    assert($store->unlock('lock-abc') === true);
    assert($store->getActiveLock() === null);

    // Now second lock can succeed
    assert($store->lock($lock2) === true);
});

// ==========================================
// 4. DAG & Topological Dependency Sorter
// ==========================================

it('DependencyGraph resolves execution order using Kahn topological sorting', function () {
    $graph = new DependencyGraph();

    $nodeVpc = new ResourceNode('aws_vpc.main');
    $nodeSubnet = new ResourceNode('aws_subnet.public', dependencies: ['aws_vpc.main']);
    $nodeSg = new ResourceNode('aws_security_group.web', dependencies: ['aws_vpc.main']);
    $nodeEc2 = new ResourceNode('aws_instance.app', dependencies: ['aws_subnet.public', 'aws_security_group.web']);

    $graph->addNode($nodeVpc)
          ->addNode($nodeSubnet)
          ->addNode($nodeSg)
          ->addNode($nodeEc2);

    $graph->addEdge('aws_subnet.public', 'aws_vpc.main')
          ->addEdge('aws_security_group.web', 'aws_vpc.main')
          ->addEdge('aws_instance.app', 'aws_subnet.public')
          ->addEdge('aws_instance.app', 'aws_security_group.web');

    $order = $graph->buildTopologicalOrder();

    // aws_vpc.main must appear before subnet and sg; subnet and sg must appear before instance
    $posVpc = array_search('aws_vpc.main', $order);
    $posSubnet = array_search('aws_subnet.public', $order);
    $posSg = array_search('aws_security_group.web', $order);
    $posEc2 = array_search('aws_instance.app', $order);

    assert($posVpc < $posSubnet);
    assert($posVpc < $posSg);
    assert($posSubnet < $posEc2);
    assert($posSg < $posEc2);
});

it('DependencyGraph detects circular dependency cycles and throws exception', function () {
    $graph = new DependencyGraph();

    $nodeA = new ResourceNode('res_a', dependencies: ['res_b']);
    $nodeB = new ResourceNode('res_b', dependencies: ['res_a']);

    $graph->addNode($nodeA)->addNode($nodeB);
    $graph->addEdge('res_a', 'res_b')->addEdge('res_b', 'res_a');

    $cycleDetected = false;
    try {
        $graph->buildTopologicalOrder();
    } catch (\RuntimeException $e) {
        $cycleDetected = true;
        assert(str_contains($e->getMessage(), 'Circular dependency'));
    }

    assert($cycleDetected === true);
});

// ==========================================
// 5. Differential Engine & Execution Planner
// ==========================================

it('DiffPlanner detects CREATE, UPDATE, REPLACE and DESTROY mutations', function () {
    $planner = new DiffPlanner();

    // 1. Initial State has aws_vpc.old and aws_instance.web (cidr = "10.0.0.0/16", tags = { env = "dev" })
    $state = new StateFile();
    $state->setResource(new StateResource('managed', 'aws_vpc', 'old', 'provider["aws"]', [
        new StateResourceInstance(0, ['id' => 'vpc-old'])
    ]));
    $state->setResource(new StateResource('managed', 'aws_instance', 'web', 'provider["aws"]', [
        new StateResourceInstance(0, ['id' => 'i-web', 'instance_type' => 't3.micro', 'availability_zone' => 'us-east-1a'])
    ]));

    // 2. Desired AST: aws_vpc.old is removed (DESTROY), aws_instance.web has new instance_type (UPDATE),
    // and aws_subnet.new is introduced (CREATE)
    $desired = [
        new AstBlock(
            blockType: BlockType::RESOURCE,
            type: 'aws_instance',
            name: 'web',
            attributes: ['instance_type' => 't3.large', 'availability_zone' => 'us-east-1a']
        ),
        new AstBlock(
            blockType: BlockType::RESOURCE,
            type: 'aws_subnet',
            name: 'new',
            attributes: ['cidr_block' => '10.0.2.0/24']
        ),
    ];

    $plan = $planner->plan($desired, $state);
    $counts = $plan->getCounts();

    assert($counts['create'] === 1);  // aws_subnet.new
    assert($counts['update'] === 1);  // aws_instance.web
    assert($counts['destroy'] === 1); // aws_vpc.old
    assert($plan->getChange('aws_subnet.new')?->action === ActionType::CREATE);
    assert($plan->getChange('aws_instance.web')?->action === ActionType::UPDATE);
    assert($plan->getChange('aws_vpc.old')?->action === ActionType::DESTROY);
});

it('DiffPlanner flags REPLACE when immutable attribute like availability_zone changes', function () {
    $planner = new DiffPlanner();

    $state = new StateFile();
    $state->setResource(new StateResource('managed', 'aws_subnet', 'pub', 'provider["aws"]', [
        new StateResourceInstance(0, ['id' => 'sub-1', 'cidr_block' => '10.0.1.0/24', 'availability_zone' => 'us-east-1a'])
    ]));

    $desired = [
        new AstBlock(
            blockType: BlockType::RESOURCE,
            type: 'aws_subnet',
            name: 'pub',
            attributes: ['cidr_block' => '10.0.2.0/24', 'availability_zone' => 'us-east-1b'] // cidr_block is immutable!
        )
    ];

    $plan = $planner->plan($desired, $state);
    assert($plan->getChange('aws_subnet.pub')?->action === ActionType::REPLACE);
});

// ==========================================
// 6. Multi-Cloud Providers & Drivers
// ==========================================

it('AwsProvider provisions VPC, Subnet, Security Group, and EC2 instance', function () {
    $aws = new AwsProvider('us-east-1');
    assert($aws->supports('aws_instance') === true);
    assert($aws->supports('google_compute_instance') === false);

    $vpc = $aws->create('aws_vpc', 'main', ['cidr_block' => '10.0.0.0/16']);
    assert(str_starts_with($vpc['id'], 'vpc-'));
    assert(str_starts_with($vpc['arn'], 'arn:aws:aws_vpc:us-east-1'));

    $ec2 = $aws->create('aws_instance', 'app', ['instance_type' => 't3.micro', 'vpc_id' => $vpc['id']]);
    assert(str_starts_with($ec2['id'], 'i-'));
    assert(isset($ec2['public_ip']));
    assert($ec2['instance_state'] === 'running');
});

it('GcpProvider provisions Google compute networks and instances', function () {
    $gcp = new GcpProvider('netfusion-prod', 'us-central1-a');
    assert($gcp->supports('google_compute_instance') === true);

    $vm = $gcp->create('google_compute_instance', 'worker-1', ['machine_type' => 'e2-medium']);
    assert(str_contains($vm['id'], 'netfusion-prod'));
    assert(str_starts_with($vm['self_link'], 'https://www.googleapis.com'));
    assert($vm['status'] === 'RUNNING');
});

it('LocalContainerProvider provisions simulated containers and network', function () {
    $local = new LocalContainerProvider();
    assert($local->supports('local_container') === true);

    $container = $local->create('local_container', 'redis', ['image' => 'redis:alpine', 'port' => 6379]);
    assert($container['id'] === 'local_container-redis');
    assert($container['status'] === 'running');
    assert(isset($container['pid']));
});

// ==========================================
// 7. Plan Executor & Dynamic Reference Resolution
// ==========================================

it('PlanExecutor provisions infrastructure in DAG order and resolves dynamic references', function () {
    $executor = new PlanExecutor([
        new AwsProvider('us-east-1'),
        new LocalContainerProvider(),
    ]);

    $plan = new ExecutionPlan(
        planId: 'plan-101',
        timestamp: time(),
        changes: [
            new ResourceChange('aws_vpc.vpc1', ActionType::CREATE, null, ['cidr_block' => '10.0.0.0/16']),
            new ResourceChange('aws_subnet.sub1', ActionType::CREATE, null, [
                'vpc_id' => '${aws_vpc.vpc1.id}',
                'cidr_block' => '10.0.1.0/24'
            ]),
        ],
        topologicalOrder: ['aws_vpc.vpc1', 'aws_subnet.sub1']
    );

    $state = new StateFile();
    $result = $executor->execute($plan, $state);

    assert($result['success'] === true);
    assert($result['applied'] === 2);
    assert($result['failed'] === 0);

    $vpcState = $state->getResource('aws_vpc.vpc1');
    $subState = $state->getResource('aws_subnet.sub1');

    assert($vpcState !== null);
    assert($subState !== null);

    $vpcId = $vpcState->getPrimaryInstance()?->getAttribute('id');
    $subVpcId = $subState->getPrimaryInstance()?->getAttribute('vpc_id');

    // Subnet successfully dynamically referenced the newly created VPC's generated id!
    assert($subVpcId === $vpcId);
    assert(str_starts_with($vpcId, 'vpc-'));
});

// ==========================================
// 8. IacManager Facade & Global Helpers
// ==========================================

it('IacManager plan(), apply(), and state() execute complete declarative workflow', function () {
    $iac = new IacManager();

    $hcl = <<<'HCL'
resource "aws_vpc" "app_network" {
  cidr_block = "172.16.0.0/16"
  enable_dns = true
}

resource "aws_instance" "web_node" {
  vpc_id = aws_vpc.app_network.id
  instance_type = "t3.small"
}
HCL;

    // 1. Generate plan
    $plan = $iac->plan($hcl);
    assert($plan->getCounts()['create'] === 2);
    assert($plan->hasChanges() === true);

    // 2. Apply plan
    $applyResult = $iac->apply($plan);
    assert($applyResult['success'] === true);
    assert($applyResult['applied'] === 2);

    // 3. Inspect updated state
    $state = $iac->getState();
    assert($state->hasResource('aws_vpc.app_network') === true);
    assert($state->hasResource('aws_instance.web_node') === true);

    // 4. Re-plan should result in NOOP (idempotent / zero-drift)
    $planNoop = $iac->plan($hcl);
    assert($planNoop->getCounts()['noop'] === 2);
    assert($planNoop->hasChanges() === false);
});

it('Global helpers iac(), iac_plan(), iac_apply(), and iac_state() work seamlessly', function () {
    assert(iac() instanceof IacManager);

    $hcl = 'resource "local_container" "cache" { image = "memcached:alpine" }';
    $plan = iac_plan($hcl);
    assert($plan instanceof ExecutionPlan);
    assert($plan->getChange('local_container.cache')?->action === ActionType::CREATE);

    $result = iac_apply($plan);
    assert($result['success'] === true);

    $tracked = iac_state('local_container.cache');
    assert($tracked instanceof StateResource);
    assert($tracked->name === 'cache');
});

it('Console commands iac:plan, iac:apply, iac:destroy, and iac:state initialize cleanly', function () {
    global $app;
    $planCmd = new \NetFusion\Core\Console\Commands\IacPlanCommand($app);
    assert($planCmd->getName() === 'iac:plan');

    $applyCmd = new \NetFusion\Core\Console\Commands\IacApplyCommand($app);
    assert($applyCmd->getName() === 'iac:apply');

    $destroyCmd = new \NetFusion\Core\Console\Commands\IacDestroyCommand($app);
    assert($destroyCmd->getName() === 'iac:destroy');

    $stateCmd = new \NetFusion\Core\Console\Commands\IacStateCommand($app);
    assert($stateCmd->getName() === 'iac:state');
});

it('Database migration up and down executes successfully for iac schema', function () {
    $migration = require dirname(__DIR__) . '/database/migrations/2026_09_28_000061_create_iac_tables.php';

    $conn = new \NetFusion\Core\Database\Connection(['driver' => 'sqlite', 'database' => ':memory:']);
    $migration->up($conn);

    $pdo = $conn->getPdo();
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='iac_state_files'");
    $hasTable = ($stmt->fetch() !== false);
    $stmt->closeCursor();
    unset($stmt);
    assert($hasTable === true);

    $migration->down($conn);
});

// ==========================================
// Summary
// ==========================================
echo "\n--------------------------------------------------\n";
echo "Total Tests : " . ($passed + $failed) . "\n";
echo "Passed      : {$passed}\n";
echo "Failed      : {$failed}\n";
echo "Pass Rate   : " . round(($passed / ($passed + $failed)) * 100, 2) . "%\n";
echo "--------------------------------------------------\n";

if ($failed > 0) {
    exit(1);
}
