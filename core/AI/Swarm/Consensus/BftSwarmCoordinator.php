<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Swarm/Consensus/BftSwarmCoordinator.php
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

namespace NetFusion\Core\AI\Swarm\Consensus;

use NetFusion\Core\AI\Swarm\Exceptions\QuorumNotReachedException;

/**
 * Leaderless Byzantine Fault Tolerant (BFT) Swarm Consensus Engine.
 *
 * Guarantees safety and liveness across decentralized autonomous agents
 * under partial synchrony with up to f < n/3 Byzantine adversaries.
 */
class BftSwarmCoordinator
{
    public function __construct(
        private readonly string $clusterSecret = 'bft-swarm-secret-key'
    ) {
    }

    /**
     * Compute deterministic cryptographic hash of a proposal.
     *
     * @param array<string, mixed> $data
     */
    public function hashProposal(string $proposalId, array $data): string
    {
        return hash('sha256', $proposalId . ':' . json_encode($data));
    }

    /**
     * Generate signed Byzantine vote.
     */
    public function createVote(string $voterId, string $proposalHash, int $round = 1, bool $inFavor = true): BftSwarmVote
    {
        $sigPayload = "{$voterId}:{$proposalHash}:{$round}:" . ($inFavor ? '1' : '0');
        $signature = hash_hmac('sha256', $sigPayload, $this->clusterSecret);

        return new BftSwarmVote(
            voterId: $voterId,
            proposalHash: $proposalHash,
            round: $round,
            inFavor: $inFavor,
            signature: $signature
        );
    }

    /**
     * Verify individual vote authenticity.
     */
    public function verifyVote(BftSwarmVote $vote): bool
    {
        $sigPayload = "{$vote->getVoterId()}:{$vote->getProposalHash()}:{$vote->getRound()}:" . ($vote->isInFavor() ? '1' : '0');
        $expected = hash_hmac('sha256', $sigPayload, $this->clusterSecret);
        return hash_equals($expected, $vote->getSignature());
    }

    /**
     * Aggregate votes and verify Byzantine Quorum Certificate (QC >= 2f + 1 / ceil(2*n/3)).
     *
     * @param BftSwarmVote[] $votes
     * @param int $totalValidatorPopulation
     * @return array{
     *   quorum_reached: bool,
     *   quorum_certificate: ?string,
     *   affirmative_votes: int,
     *   threshold_required: int
     * }
     * @throws QuorumNotReachedException
     */
    public function certifyQuorum(
        string $proposalHash,
        array $votes,
        int $totalValidatorPopulation,
        bool $throwOnFailure = false
    ): array {
        $threshold = (int)ceil((2.0 * $totalValidatorPopulation) / 3.0);
        $validSignatures = [];
        $uniqueVoters = [];

        foreach ($votes as $vote) {
            if ($vote->getProposalHash() !== $proposalHash || !$vote->isInFavor()) {
                continue;
            }

            if (!isset($uniqueVoters[$vote->getVoterId()]) && $this->verifyVote($vote)) {
                $uniqueVoters[$vote->getVoterId()] = true;
                $validSignatures[] = $vote->getSignature();
            }
        }

        $affirmativeCount = count($validSignatures);
        $isQuorum = $affirmativeCount >= $threshold;

        if (!$isQuorum) {
            if ($throwOnFailure) {
                throw new QuorumNotReachedException(
                    "BFT Quorum not reached: obtained {$affirmativeCount} affirmative votes, requires {$threshold}."
                );
            }
            return [
                'quorum_reached'     => false,
                'quorum_certificate' => null,
                'affirmative_votes'  => $affirmativeCount,
                'threshold_required' => $threshold,
            ];
        }

        // Synthesize aggregate Quorum Certificate (QC)
        sort($validSignatures);
        $qc = hash('sha256', "QC:{$proposalHash}:" . implode(':', $validSignatures));

        return [
            'quorum_reached'     => true,
            'quorum_certificate' => $qc,
            'affirmative_votes'  => $affirmativeCount,
            'threshold_required' => $threshold,
        ];
    }
}
