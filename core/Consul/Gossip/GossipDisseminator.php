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

namespace NetFusion\Core\Consul\Gossip;

use NetFusion\Core\Consul\Enums\NodeStatus;
use NetFusion\Core\Consul\Gossip\Messages\GossipMessage;
use NetFusion\Core\Consul\Models\GossipNode;

/**
 * Disseminates state updates (alive, suspect, dead, join, leave) across the gossip mesh.
 * Handles Lamport incarnation conflicts (a node refuting suspicion by incrementing incarnation number).
 */
class GossipDisseminator
{
    protected GossipNode $localNode;
    protected SwimFailureDetector $failureDetector;
    /** @var array<GossipMessage> */
    protected array $broadcastQueue = [];
    protected int $fanout;

    public function __construct(GossipNode $localNode, SwimFailureDetector $failureDetector, int $fanout = 3)
    {
        $this->localNode = $localNode;
        $this->failureDetector = $failureDetector;
        $this->fanout = max(1, $fanout);
    }

    /**
     * Broadcast a new gossip event into the queue.
     */
    public function broadcast(string $type, GossipNode $subjectNode, array $extra = []): GossipMessage
    {
        $payload = array_merge([
            'subjectId' => $subjectNode->getId(),
            'name' => $subjectNode->getName(),
            'address' => $subjectNode->getAddress(),
            'port' => $subjectNode->getPort(),
            'status' => $subjectNode->getStatus()->value,
        ], $extra);

        $msg = new GossipMessage(
            $type,
            $this->localNode->getId(),
            null, // broadcast to all
            random_int(1, 1000000),
            $subjectNode->getIncarnation(),
            $payload
        );

        $this->broadcastQueue[] = $msg;
        return $msg;
    }

    /**
     * Ingest and apply an incoming gossip message according to SWIM conflict resolution rules.
     */
    public function handleIncomingMessage(GossipMessage $message): bool
    {
        $type = $message->getType();
        $payload = $message->getPayload();
        $subjectId = (string)($payload['subjectId'] ?? $message->getSourceNodeId());
        $msgIncarnation = $message->getIncarnation();

        // Check if subject is the local node itself
        if ($subjectId === $this->localNode->getId()) {
            if ($type === GossipMessage::TYPE_SUSPECT || $type === GossipMessage::TYPE_DEAD) {
                // Refutation: if someone suspects or declares us dead at <= our incarnation, refute by bumping incarnation!
                if ($msgIncarnation >= $this->localNode->getIncarnation()) {
                    $this->localNode->setIncarnation($msgIncarnation + 1);
                    $this->localNode->setStatus(NodeStatus::ALIVE);
                    // Broadcast ALIVE refutation to network
                    $this->broadcast(GossipMessage::TYPE_ALIVE, $this->localNode);
                    return true;
                }
            }
            return false;
        }

        // Processing remote node status update
        $node = $this->failureDetector->getMember($subjectId);
        if ($node === null) {
            // New node discovered via gossip
            if ($type !== GossipMessage::TYPE_DEAD && $type !== GossipMessage::TYPE_LEAVE) {
                $status = NodeStatus::tryFrom((string)($payload['status'] ?? 'alive')) ?? NodeStatus::ALIVE;
                $newNode = new GossipNode(
                    $subjectId,
                    (string)($payload['name'] ?? $subjectId),
                    (string)($payload['address'] ?? '127.0.0.1'),
                    (int)($payload['port'] ?? 8301),
                    $status,
                    $msgIncarnation,
                    (array)($payload['metadata'] ?? [])
                );
                $this->failureDetector->addMember($newNode);
                return true;
            }
            return false;
        }

        // Apply SWIM State Conflict Resolution Matrix:
        // - Dead message with equal or higher incarnation wins
        // - Higher incarnation always overrides lower incarnation
        // - At equal incarnation: DEAD > SUSPECT > ALIVE
        $currentIncarnation = $node->getIncarnation();
        $currentStatus = $node->getStatus();

        if ($type === GossipMessage::TYPE_LEAVE) {
            $node->setStatus(NodeStatus::LEFT);
            return true;
        }

        if ($msgIncarnation > $currentIncarnation) {
            $node->setIncarnation($msgIncarnation);
            $node->setStatus(match ($type) {
                GossipMessage::TYPE_SUSPECT => NodeStatus::SUSPECT,
                GossipMessage::TYPE_DEAD => NodeStatus::DEAD,
                default => NodeStatus::ALIVE,
            });
            $node->touch();
            return true;
        }

        if ($msgIncarnation === $currentIncarnation) {
            if ($type === GossipMessage::TYPE_DEAD && $currentStatus !== NodeStatus::DEAD) {
                $node->setStatus(NodeStatus::DEAD);
                return true;
            }
            if ($type === GossipMessage::TYPE_SUSPECT && $currentStatus === NodeStatus::ALIVE) {
                $node->setStatus(NodeStatus::SUSPECT);
                return true;
            }
        }

        return false;
    }

    /**
     * Propagate messages in broadcast queue to random k peers.
     *
     * @return int count of messages disseminated
     */
    public function disseminate(callable $sendCallback): int
    {
        if (empty($this->broadcastQueue)) {
            return 0;
        }

        $alivePeers = array_filter(
            $this->failureDetector->getMembers(),
            fn (GossipNode $n) => $n->getId() !== $this->localNode->getId() && $n->getStatus() === NodeStatus::ALIVE
        );

        if (empty($alivePeers)) {
            $count = count($this->broadcastQueue);
            $this->broadcastQueue = [];
            return $count;
        }

        $peers = array_values($alivePeers);
        shuffle($peers);
        $targets = array_slice($peers, 0, min($this->fanout, count($peers)));

        $sentCount = 0;
        while (!empty($this->broadcastQueue)) {
            $msg = array_shift($this->broadcastQueue);
            foreach ($targets as $target) {
                $sendCallback($msg, $target);
            }
            $sentCount++;
        }

        return $sentCount;
    }

    /**
     * @return array<GossipMessage>
     */
    public function getBroadcastQueue(): array
    {
        return $this->broadcastQueue;
    }

    public function clearQueue(): self
    {
        $this->broadcastQueue = [];
        return $this;
    }
}
