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

namespace NetFusion\Core\Collaboration\WebRTC;

/**
 * Class SignalingRoom
 *
 * Real-time WebRTC Signaling Room facilitating peer discovery, SDP exchanges,
 * and ICE candidate relay for P2P mesh network formation.
 */
class SignalingRoom
{
    /** @var array<string, array{peerId: string, metadata: array, joinedAt: float, lastSeen: float}> */
    private array $peers = [];

    /** @var array<string, array<int, array{type: string, data: array, timestamp: float}>> */
    private array $messageInboxes = [];

    public function __construct(
        public readonly string $roomId
    ) {
    }

    public function getRoomId(): string
    {
        return $this->roomId;
    }

    /**
     * Join a peer to this signaling room.
     */
    public function join(string $peerId, array $metadata = []): void
    {
        $now = microtime(true);
        $this->peers[$peerId] = [
            'peerId' => $peerId,
            'metadata' => $metadata,
            'joinedAt' => $now,
            'lastSeen' => $now,
        ];

        if (!isset($this->messageInboxes[$peerId])) {
            $this->messageInboxes[$peerId] = [];
        }

        // Notify other peers in the room
        $this->broadcast([
            'type' => 'peer-joined',
            'data' => [
                'peerId' => $peerId,
                'metadata' => $metadata,
            ],
            'timestamp' => $now,
        ], exceptPeer: $peerId);
    }

    /**
     * Remove a peer from this signaling room.
     */
    public function leave(string $peerId): void
    {
        if (isset($this->peers[$peerId])) {
            unset($this->peers[$peerId]);
            unset($this->messageInboxes[$peerId]);

            $this->broadcast([
                'type' => 'peer-left',
                'data' => [
                    'peerId' => $peerId,
                ],
                'timestamp' => microtime(true),
            ], exceptPeer: $peerId);
        }
    }

    public function hasPeer(string $peerId): bool
    {
        return isset($this->peers[$peerId]);
    }

    public function getPeers(): array
    {
        return $this->peers;
    }

    public function getPeerCount(): int
    {
        return count($this->peers);
    }

    /**
     * Route an SDP offer/answer to target peer or broadcast.
     */
    public function sendSdp(SdpMessage $sdp): void
    {
        $payload = [
            'type' => 'sdp',
            'data' => $sdp->toArray(),
            'timestamp' => microtime(true),
        ];

        if ($sdp->toPeer !== null) {
            $this->deliverToPeer($sdp->toPeer, $payload);
        } else {
            $this->broadcast($payload, exceptPeer: $sdp->fromPeer);
        }
    }

    /**
     * Route an ICE candidate to target peer or broadcast.
     */
    public function sendIce(IceCandidate $ice): void
    {
        $payload = [
            'type' => 'ice',
            'data' => $ice->toArray(),
            'timestamp' => microtime(true),
        ];

        if ($ice->toPeer !== null) {
            $this->deliverToPeer($ice->toPeer, $payload);
        } else {
            $this->broadcast($payload, exceptPeer: $ice->fromPeer);
        }
    }

    /**
     * Poll and drain pending signaling messages for a peer.
     *
     * @return array<int, array{type: string, data: array, timestamp: float}>
     */
    public function pollMessages(string $peerId): array
    {
        if (isset($this->peers[$peerId])) {
            $this->peers[$peerId]['lastSeen'] = microtime(true);
        }

        $messages = $this->messageInboxes[$peerId] ?? [];
        $this->messageInboxes[$peerId] = []; // Clear inbox upon retrieval
        return $messages;
    }

    private function deliverToPeer(string $peerId, array $message): void
    {
        if (isset($this->peers[$peerId])) {
            $this->messageInboxes[$peerId][] = $message;
        }
    }

    private function broadcast(array $message, ?string $exceptPeer = null): void
    {
        foreach ($this->peers as $pId => $peer) {
            if ($exceptPeer !== null && $pId === $exceptPeer) {
                continue;
            }
            $this->messageInboxes[$pId][] = $message;
        }
    }
}
