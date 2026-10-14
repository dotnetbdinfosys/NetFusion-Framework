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

namespace NetFusion\Core\DisasterRecovery;

use NetFusion\Core\DisasterRecovery\Cdp\CdpEngine;
use NetFusion\Core\DisasterRecovery\Cluster\ClusterNode;
use NetFusion\Core\DisasterRecovery\Cluster\HeartbeatMonitor;
use NetFusion\Core\DisasterRecovery\Cluster\QuorumManager;
use NetFusion\Core\DisasterRecovery\Drill\DrillExecution;
use NetFusion\Core\DisasterRecovery\Drill\DrillOrchestrator;
use NetFusion\Core\DisasterRecovery\Routing\DnsProviderInterface;
use NetFusion\Core\DisasterRecovery\Routing\SimulatedDnsDriver;
use NetFusion\Core\DisasterRecovery\Routing\TrafficRouter;
use RuntimeException;

/**
 * Class FailoverManager
 *
 * Central Master Orchestrator coordinating Autonomous Disaster Recovery:
 * - Continuous Data Protection (CDP) & Point-in-Time Recovery
 * - Split-Brain Prevention & Monotonic Fencing Tokens
 * - Quorum Consensus Election
 * - Multi-Cloud DNS Traffic Redirection
 * - Automated DR Drills and SLA Auditing
 */
class FailoverManager
{
    protected CdpEngine $cdp;
    protected QuorumManager $quorum;
    protected TrafficRouter $router;
    protected DrillOrchestrator $drills;
    protected HeartbeatMonitor $heartbeat;

    public function __construct(
        ?CdpEngine $cdp = null,
        ?QuorumManager $quorum = null,
        ?TrafficRouter $router = null,
        ?DrillOrchestrator $drills = null,
        ?HeartbeatMonitor $heartbeat = null
    ) {
        $this->cdp = $cdp ?? new CdpEngine();
        $this->quorum = $quorum ?? new QuorumManager();
        $this->heartbeat = $heartbeat ?? new HeartbeatMonitor();

        if ($router === null) {
            $dns = new SimulatedDnsDriver();
            $this->router = new TrafficRouter($dns);
        } else {
            $this->router = $router;
        }

        $this->drills = $drills ?? new DrillOrchestrator(
            $this->cdp,
            $this->quorum,
            $this->router
        );
    }

    public function getCdp(): CdpEngine
    {
        return $this->cdp;
    }

    public function getQuorum(): QuorumManager
    {
        return $this->quorum;
    }

    public function getRouter(): TrafficRouter
    {
        return $this->router;
    }

    public function getDrills(): DrillOrchestrator
    {
        return $this->drills;
    }

    public function getHeartbeat(): HeartbeatMonitor
    {
        return $this->heartbeat;
    }

    /**
     * Register a regional DR cluster node.
     */
    public function registerNode(
        string $nodeId,
        string $region,
        string $role = ClusterNode::ROLE_STANDBY,
        string $endpoint = 'http://127.0.0.1:8000'
    ): ClusterNode {
        $node = new ClusterNode($nodeId, $region, $role, ClusterNode::STATUS_ONLINE, $endpoint);
        $this->quorum->registerNode($node);
        $this->heartbeat->recordHeartbeat($nodeId);
        return $node;
    }

    /**
     * Execute automated or manual failover to promote a target Standby node to Primary.
     *
     * @param string $targetNodeId
     * @param bool $force Bypass quorum checks (emergency only)
     * @return array
     */
    public function promoteStandby(string $targetNodeId, bool $force = false): array
    {
        $startTime = microtime(true);
        $candidate = $this->quorum->getNode($targetNodeId);

        if ($candidate === null) {
            throw new RuntimeException("Target node '{$targetNodeId}' not registered");
        }

        // 1. Quorum Election & Fencing Token Generation
        $election = $this->quorum->electPrimary($targetNodeId, $force);
        if (!$election['success']) {
            return [
                'success' => false,
                'reason'  => $election['reason'],
                'epoch'   => $this->quorum->getCurrentEpoch(),
            ];
        }

        $token = $election['fencing_token'];

        // 2. Divert DNS Traffic to the new Primary's endpoint
        $divertedDomains = [];
        foreach ($this->router->getDomains() as $domain => $config) {
            $this->router->divertTraffic($domain, $candidate->endpoint, 15);
            $divertedDomains[] = $domain;
        }

        // 3. Update CDP engine region
        $this->cdp->setRegion($candidate->region);

        $endTime = microtime(true);
        $rto = round($endTime - $startTime, 4);
        $rpo = $this->cdp->measureRpo($endTime);

        return [
            'success'          => true,
            'primary_node'     => $targetNodeId,
            'primary_region'   => $candidate->region,
            'epoch'            => $election['epoch'],
            'fencing_token'    => $token->toArray(),
            'diverted_domains' => $divertedDomains,
            'measured_rto_sec' => $rto,
            'measured_rpo_sec' => $rpo,
            'reason'           => $election['reason'],
        ];
    }

    /**
     * Graceful switchover without disruption.
     */
    public function gracefulSwitchover(string $newPrimaryNodeId): array
    {
        $oldPrimary = $this->quorum->getPrimaryNode();
        $res = $this->promoteStandby($newPrimaryNodeId, false);

        if ($res['success'] && $oldPrimary !== null) {
            // Demote old primary to standby and unfence
            $oldPrimary->role = ClusterNode::ROLE_STANDBY;
            $oldPrimary->unfence();
        }

        return $res;
    }

    /**
     * Failback to the original primary region.
     */
    public function failback(string $originalNodeId): array
    {
        $node = $this->quorum->getNode($originalNodeId);
        if ($node === null) {
            throw new RuntimeException("Original node '{$originalNodeId}' not found");
        }

        $node->unfence();
        return $this->gracefulSwitchover($originalNodeId);
    }

    /**
     * Run an automated non-disruptive DR drill.
     */
    public function runDrill(string $targetRegion): DrillExecution
    {
        return $this->drills->runDrill($targetRegion);
    }

    /**
     * Get real-time cluster Disaster Recovery status summary.
     */
    public function getStatus(): array
    {
        $primary = $this->quorum->getPrimaryNode();
        $nodesData = [];
        foreach ($this->quorum->getNodes() as $node) {
            $nodesData[] = $node->toArray();
        }

        return [
            'primary_node'   => $primary?->nodeId,
            'primary_region' => $primary?->region,
            'cluster_epoch'  => $this->quorum->getCurrentEpoch(),
            'has_quorum'     => $this->quorum->hasQuorum(),
            'online_nodes'   => $this->quorum->getOnlineNodeCount(),
            'total_nodes'    => $this->quorum->getNodeCount(),
            'quorum_min'     => $this->quorum->getQuorumThreshold(),
            'cdp_records'    => $this->cdp->getJournal()->count(),
            'current_rpo_sec'=> $this->cdp->measureRpo(),
            'nodes'          => $nodesData,
        ];
    }
}
