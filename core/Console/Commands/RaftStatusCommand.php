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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\DistributedDb\DistributedDbManager;

/**
 * Class RaftStatusCommand
 *
 * CLI: php netfusion raft:status [cluster]
 */
class RaftStatusCommand extends Command
{
    protected string $name = 'raft:status';
    protected string $description = 'Display status, leader, quorum, and log indices of a Raft consensus cluster';

    public function handle(array $args = []): int
    {
        $clusterName = (string) $this->argument(0, 'default');

        /** @var DistributedDbManager $dbManager */
        $dbManager = app(DistributedDbManager::class);
        $cluster = $dbManager->raft($clusterName);

        $leader = $cluster->getLeader();
        $leaderId = $leader ? $leader->getNodeId() : 'NONE (Election pending)';

        $this->info("=== Raft Consensus Cluster: [{$clusterName}] ===");
        $this->line("Nodes Count : " . $cluster->getNodeCount());
        $this->line("Quorum Size : " . $cluster->getQuorum());
        $this->line("Active Leader: {$leaderId}");
        $this->line("--------------------------------------------------");

        foreach ($cluster->getNodes() as $nodeId => $node) {
            $role = $node->getRole()->value;
            $term = $node->getCurrentTerm();
            $lastIndex = $node->getLastLogIndex();
            $commitIndex = $node->getCommitIndex();
            $lastApplied = $node->getLastApplied();

            $this->line("Node: {$nodeId} | Role: {$role} | Term: {$term} | LastLogIndex: {$lastIndex} | CommitIndex: {$commitIndex} | Applied: {$lastApplied}");
        }

        return 0;
    }
}
