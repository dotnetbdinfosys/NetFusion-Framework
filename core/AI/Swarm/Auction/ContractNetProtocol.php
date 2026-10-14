<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Swarm/Auction/ContractNetProtocol.php
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

namespace NetFusion\Core\AI\Swarm\Auction;

use NetFusion\Core\AI\Swarm\Agents\SwarmAgent;
use NetFusion\Core\AI\Swarm\Enums\AgentRole;
use NetFusion\Core\AI\Swarm\Enums\AuctionState;

/**
 * Decentralized Contract Net Protocol (CNP) Task Auction Engine.
 *
 * Implements FIPA-compliant multi-agent CFP broadcast, bid ranking,
 * contract award, and execution dispatch.
 */
class ContractNetProtocol
{
    /**
     * Conduct complete decentralized auction lifecycle for a task proposal.
     *
     * @param TaskProposal $proposal
     * @param SwarmAgent[] $agents
     * @return array{
     *   task_id: string,
     *   state: AuctionState,
     *   total_bids: int,
     *   awarded_agent: ?string,
     *   winning_score: float,
     *   execution: ?array
     * }
     */
    public function conductAuction(TaskProposal $proposal, array $agents): array
    {
        // 1. Broadcast Call for Proposals (CFP) to all eligible worker agents
        $bids = [];
        $agentMap = [];

        foreach ($agents as $agent) {
            $agentMap[$agent->getId()] = $agent;
            if ($agent->getRole() === AgentRole::WORKER || $agent->getRole() === AgentRole::SCOUT) {
                $bid = $agent->createBid($proposal);
                if ($bid !== null) {
                    $bids[] = $bid;
                }
            }
        }

        if (empty($bids)) {
            return [
                'task_id'       => $proposal->getId(),
                'state'         => AuctionState::FAILED,
                'total_bids'    => 0,
                'awarded_agent' => null,
                'winning_score' => 0.0,
                'execution'     => null,
            ];
        }

        // 2. Select winning bid: highest bidScore (best combination of power, idle capacity & low cost)
        usort($bids, fn(AgentBid $a, AgentBid $b) => $b->getBidScore() <=> $a->getBidScore());
        $winningBid = $bids[0];
        $winningAgentId = $winningBid->getAgentId();
        $winningAgent = $agentMap[$winningAgentId] ?? null;

        // 3. Award and execute contract
        $executionResult = null;
        if ($winningAgent !== null) {
            $executionResult = $winningAgent->executeTask($proposal);
        }

        return [
            'task_id'       => $proposal->getId(),
            'state'         => AuctionState::COMPLETED,
            'total_bids'    => count($bids),
            'awarded_agent' => $winningAgentId,
            'winning_score' => $winningBid->getBidScore(),
            'execution'     => $executionResult,
        ];
    }
}
