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
 * Implements the SWIM (Structured Weakly-Consistent Infection-Style Process Group Membership) Failure Detector.
 * Provides direct ping probing, indirect ping-req via randomly selected peers, suspect timeouts, and dead transitions.
 */
class SwimFailureDetector
{
    protected GossipNode $localNode;
    /** @var array<string, GossipNode> */
    protected array $members = [];
    protected float $pingTimeout; // seconds (e.g. 0.2s = 200ms)
    protected float $indirectPingTimeout; // seconds (e.g. 0.5s = 500ms)
    protected float $suspectTimeout; // seconds before SUSPECT becomes DEAD (e.g. 2.0s)
    protected int $indirectPingPeersCount; // k peers for indirect ping-req (e.g. 3)

    /** @var array<int, array{target: string, sentAt: float, type: string}> */
    protected array $pendingPings = [];

    /** @var callable|null function(GossipMessage, GossipNode): bool */
    protected $transportSender = null;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(GossipNode $localNode, array $options = [])
    {
        $this->localNode = $localNode;
        $this->pingTimeout = (float)($options['pingTimeout'] ?? 0.5);
        $this->indirectPingTimeout = (float)($options['indirectPingTimeout'] ?? 1.0);
        $this->suspectTimeout = (float)($options['suspectTimeout'] ?? 3.0);
        $this->indirectPingPeersCount = (int)($options['indirectPingPeersCount'] ?? 3);

        $this->addMember($localNode);
    }

    public function getLocalNode(): GossipNode
    {
        return $this->localNode;
    }

    public function setTransportSender(callable $sender): self
    {
        $this->transportSender = $sender;
        return $this;
    }

    public function addMember(GossipNode $node): self
    {
        $this->members[$node->getId()] = $node;
        return $this;
    }

    public function removeMember(string $nodeId): self
    {
        if ($nodeId !== $this->localNode->getId()) {
            unset($this->members[$nodeId]);
        }
        return $this;
    }

    public function getMember(string $nodeId): ?GossipNode
    {
        return $this->members[$nodeId] ?? null;
    }

    /**
     * @return array<string, GossipNode>
     */
    public function getMembers(): array
    {
        return $this->members;
    }

    /**
     * @return array<string, GossipNode>
     */
    public function getAliveMembers(): array
    {
        return array_filter($this->members, fn (GossipNode $n) => $n->getStatus() === NodeStatus::ALIVE);
    }

    /**
     * Send direct ping to target node.
     * Returns true if ACK received directly or via transport.
     */
    public function ping(GossipNode $target): bool
    {
        if ($target->getId() === $this->localNode->getId()) {
            return true;
        }

        $message = new GossipMessage(
            GossipMessage::TYPE_PING,
            $this->localNode->getId(),
            $target->getId(),
            random_int(1, 1000000),
            $this->localNode->getIncarnation()
        );

        if ($this->transportSender !== null) {
            $acked = ($this->transportSender)($message, $target);
            if ($acked) {
                $target->touch();
                if ($target->getStatus() === NodeStatus::SUSPECT) {
                    $target->setStatus(NodeStatus::ALIVE);
                }
                return true;
            }
            return false;
        }

        // If in-memory simulation, target is alive if its status is ALIVE
        if ($target->getStatus() === NodeStatus::ALIVE) {
            $target->touch();
            return true;
        }

        return false;
    }

    /**
     * Send indirect ping-req through k peers to verify target health.
     */
    public function pingRequest(GossipNode $target): bool
    {
        $eligiblePeers = array_filter($this->members, function (GossipNode $node) use ($target) {
            return $node->getId() !== $this->localNode->getId()
                && $node->getId() !== $target->getId()
                && $node->getStatus() === NodeStatus::ALIVE;
        });

        if (empty($eligiblePeers)) {
            return false;
        }

        $peers = array_values($eligiblePeers);
        shuffle($peers);
        $selectedPeers = array_slice($peers, 0, $this->indirectPingPeersCount);

        $indirectSuccess = false;
        foreach ($selectedPeers as $proxyNode) {
            $msg = new GossipMessage(
                GossipMessage::TYPE_PING_REQ,
                $this->localNode->getId(),
                $proxyNode->getId(),
                random_int(1, 1000000),
                $this->localNode->getIncarnation(),
                ['targetId' => $target->getId(), 'targetAddress' => $target->getAddress(), 'targetPort' => $target->getPort()]
            );

            if ($this->transportSender !== null) {
                if (($this->transportSender)($msg, $proxyNode)) {
                    $indirectSuccess = true;
                    break;
                }
            } else {
                // Simulation: if proxy is alive and target is alive, proxy confirms
                if ($proxyNode->getStatus() === NodeStatus::ALIVE && $target->getStatus() === NodeStatus::ALIVE) {
                    $indirectSuccess = true;
                    break;
                }
            }
        }

        if ($indirectSuccess) {
            $target->touch();
            if ($target->getStatus() === NodeStatus::SUSPECT) {
                $target->setStatus(NodeStatus::ALIVE);
            }
            return true;
        }

        return false;
    }

    /**
     * Execute one probe round of the SWIM protocol on a selected or random target node.
     * Direct Ping -> If timeout -> Indirect Ping-Req -> If fail -> Mark Suspect.
     */
    public function probeNode(GossipNode $target): NodeStatus
    {
        if ($target->getId() === $this->localNode->getId()) {
            return NodeStatus::ALIVE;
        }

        // 1. Direct Ping
        $directAck = $this->ping($target);
        if ($directAck) {
            return NodeStatus::ALIVE;
        }

        // 2. Indirect Ping-Req
        $indirectAck = $this->pingRequest($target);
        if ($indirectAck) {
            return NodeStatus::ALIVE;
        }

        // 3. Mark Suspect if failed
        $target->setStatus(NodeStatus::SUSPECT);
        return NodeStatus::SUSPECT;
    }

    /**
     * Check all suspect members and mark them DEAD if suspectTimeout has elapsed.
     * Returns array of nodes that transitioned to DEAD.
     *
     * @return array<GossipNode>
     */
    public function checkSuspectTimeouts(float $customCurrentTime = 0.0): array
    {
        $now = $customCurrentTime > 0.0 ? $customCurrentTime : microtime(true);
        $deadNodes = [];

        foreach ($this->members as $node) {
            if ($node->getStatus() === NodeStatus::SUSPECT && $node->getSuspectSince() !== null) {
                if (($now - $node->getSuspectSince()) >= $this->suspectTimeout) {
                    $node->setStatus(NodeStatus::DEAD);
                    $deadNodes[] = $node;
                }
            }
        }

        return $deadNodes;
    }
}
