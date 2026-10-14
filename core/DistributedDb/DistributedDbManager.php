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

namespace NetFusion\Core\DistributedDb;

use NetFusion\Core\DistributedDb\DistributedSql\DistributedQueryPlanner;
use NetFusion\Core\DistributedDb\DistributedSql\ScatterGatherExecutor;
use NetFusion\Core\DistributedDb\MultiMaster\ConflictResolutionStrategy;
use NetFusion\Core\DistributedDb\MultiMaster\MultiMasterCoordinator;
use NetFusion\Core\DistributedDb\MultiMaster\ReplicationMode;
use NetFusion\Core\DistributedDb\Raft\RaftCluster;
use NetFusion\Core\DistributedDb\Raft\RaftNode;
use NetFusion\Core\DistributedDb\TwoPhaseCommit\DistributedLockManager;
use NetFusion\Core\DistributedDb\TwoPhaseCommit\TwoPhaseCoordinator;

/**
 * Master facade coordinating Raft consensus, Geo multi-master replication,
 * Distributed SQL scatter-gather execution, and Two-Phase Commit transactions.
 */
class DistributedDbManager
{
    /**
     * @var array<string, RaftCluster>
     */
    private array $raftClusters = [];

    private MultiMasterCoordinator $multiMasterCoordinator;
    private DistributedQueryPlanner $queryPlanner;
    private ScatterGatherExecutor $scatterGatherExecutor;
    private TwoPhaseCoordinator $twoPhaseCoordinator;
    private DistributedLockManager $lockManager;

    public function __construct()
    {
        $this->multiMasterCoordinator = new MultiMasterCoordinator(
            mode: ReplicationMode::ASYNC,
            strategy: ConflictResolutionStrategy::LAST_WRITE_WINS
        );
        $this->queryPlanner = new DistributedQueryPlanner();
        $this->scatterGatherExecutor = new ScatterGatherExecutor();
        $this->twoPhaseCoordinator = new TwoPhaseCoordinator();
        $this->lockManager = new DistributedLockManager();

        // Initialize default Raft cluster with 3 default local nodes
        $defaultCluster = new RaftCluster([
            new RaftNode('node-1'),
            new RaftNode('node-2'),
            new RaftNode('node-3'),
        ]);
        $this->raftClusters['default'] = $defaultCluster;
    }

    /**
     * Access a named Raft cluster.
     */
    public function raft(string $clusterName = 'default'): RaftCluster
    {
        if (!isset($this->raftClusters[$clusterName])) {
            $this->raftClusters[$clusterName] = new RaftCluster();
        }

        return $this->raftClusters[$clusterName];
    }

    /**
     * Access the Geo Multi-Master replication coordinator.
     */
    public function multiMaster(): MultiMasterCoordinator
    {
        return $this->multiMasterCoordinator;
    }

    /**
     * Access the Distributed SQL query planner.
     */
    public function planner(): DistributedQueryPlanner
    {
        return $this->queryPlanner;
    }

    /**
     * Access the Distributed SQL scatter-gather executor.
     */
    public function executor(): ScatterGatherExecutor
    {
        return $this->scatterGatherExecutor;
    }

    /**
     * Execute a distributed SQL query via planner and scatter-gather executor.
     *
     * @param string $sql
     * @param array<mixed> $params
     * @return array<mixed>
     */
    public function query(string $sql, array $params = []): array
    {
        $plan = $this->queryPlanner->plan($sql, $params);
        return $this->scatterGatherExecutor->execute($plan);
    }

    /**
     * Access the Two-Phase Commit (2PC) coordinator.
     */
    public function twoPhase(): TwoPhaseCoordinator
    {
        return $this->twoPhaseCoordinator;
    }

    /**
     * Access the Distributed Lock manager.
     */
    public function lock(): DistributedLockManager
    {
        return $this->lockManager;
    }
}
