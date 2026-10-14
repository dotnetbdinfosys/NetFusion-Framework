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

namespace NetFusion\Core\IaC;

use NetFusion\Core\IaC\Execution\PlanExecutor;
use NetFusion\Core\IaC\Parser\HclParser;
use NetFusion\Core\IaC\Plan\ExecutionPlan;
use NetFusion\Core\IaC\Planner\DiffPlanner;
use NetFusion\Core\IaC\Providers\Aws\AwsProvider;
use NetFusion\Core\IaC\Providers\Contracts\ResourceProviderInterface;
use NetFusion\Core\IaC\Providers\Gcp\GcpProvider;
use NetFusion\Core\IaC\Providers\Local\LocalContainerProvider;
use NetFusion\Core\IaC\State\Contracts\StateStoreInterface;
use NetFusion\Core\IaC\State\StateFile;
use NetFusion\Core\IaC\State\StateLock;
use NetFusion\Core\IaC\State\Stores\MemoryStateStore;

/**
 * Unified master facade coordinating IaC HCL parsing, state storage,
 * dependency DAG planning, and multi-cloud execution.
 */
class IacManager
{
    private static ?self $instance = null;

    private HclParser $parser;
    private DiffPlanner $planner;
    private StateStoreInterface $stateStore;
    private PlanExecutor $executor;

    public static function getInstance(?StateStoreInterface $stateStore = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($stateStore);
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function __construct(?StateStoreInterface $stateStore = null)
    {
        $this->parser = new HclParser();
        $this->planner = new DiffPlanner();
        $this->stateStore = $stateStore ?? new MemoryStateStore(new StateFile());

        $this->executor = new PlanExecutor([
            new AwsProvider(),
            new GcpProvider(),
            new LocalContainerProvider(),
        ]);
    }

    public function getStateStore(): StateStoreInterface
    {
        return $this->stateStore;
    }

    public function setStateStore(StateStoreInterface $store): self
    {
        $this->stateStore = $store;
        return $this;
    }

    public function addProvider(ResourceProviderInterface $provider): self
    {
        $this->executor->addProvider($provider);
        return $this;
    }

    public function getState(): StateFile
    {
        return $this->stateStore->read() ?? new StateFile();
    }

    /**
     * Parse HCL and compute execution diff against state.
     *
     * @param string $hcl
     * @param array<string, mixed> $variables
     * @return ExecutionPlan
     */
    public function plan(string $hcl, array $variables = []): ExecutionPlan
    {
        $astBlocks = $this->parser->parse($hcl, $variables);
        $currentState = $this->getState();

        return $this->planner->plan($astBlocks, $currentState, $variables);
    }

    /**
     * Apply planned mutations or HCL configuration directly.
     *
     * @param ExecutionPlan|string $planOrHcl
     * @param array<string, mixed> $variables
     * @return array{success: bool, applied: int, failed: int, state: StateFile, logs: array<string>}
     */
    public function apply(ExecutionPlan|string $planOrHcl, array $variables = []): array
    {
        $lock = new StateLock(
            lockId: uniqid('lock_', true),
            operation: 'Apply',
            who: 'netfusion@cli',
            created: time()
        );

        if (!$this->stateStore->lock($lock)) {
            throw new \RuntimeException("Could not acquire exclusive state lock. Another process is modifying state.");
        }

        try {
            $plan = is_string($planOrHcl) ? $this->plan($planOrHcl, $variables) : $planOrHcl;
            $currentState = $this->getState();

            $result = $this->executor->execute($plan, $currentState);
            $this->stateStore->write($result['state']);

            return $result;
        } finally {
            $this->stateStore->unlock($lock->lockId);
        }
    }

    /**
     * Plan and execute a full infrastructure teardown (destroy all resources).
     */
    public function destroy(string $hcl = '', array $variables = []): array
    {
        // Destroy plan: desired state is empty
        $currentState = $this->getState();
        $plan = $this->planner->plan([], $currentState, $variables);

        return $this->apply($plan, $variables);
    }
}
