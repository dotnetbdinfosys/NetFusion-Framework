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

namespace NetFusion\Core\Media\SFU;

use NetFusion\Core\Media\Enums\SimulcastLayer;
use NetFusion\Core\Media\Exceptions\MediaException;
use NetFusion\Core\Media\Exceptions\PeerNotFoundException;
use NetFusion\Core\Media\Rtp\RtcpPacket;
use NetFusion\Core\Media\Rtp\RtpPacket;

/**
 * Selective Forwarding Unit (SFU) Conference Room.
 * Manages multi-party audio/video routing, active speaker detection,
 * keyframe requests (PLI), and Simulcast layer adaptation.
 */
class SfuRoom
{
    protected string $id;
    protected string $name;
    protected int $maxPeers;
    /** @var array<string, PeerConnection> */
    protected array $peers = [];
    protected SimulcastRouter $router;
    protected int $forwardedPacketsCount = 0;
    /** @var callable|null function(string $publisherId, string $trackId, RtcpPacket $pli): void */
    protected $keyframeCallback = null;

    public function __construct(string $id, string $name = 'Default Conference', int $maxPeers = 100)
    {
        $this->id = $id;
        $this->name = $name;
        $this->maxPeers = $maxPeers;
        $this->router = new SimulcastRouter();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMaxPeers(): int
    {
        return $this->maxPeers;
    }

    public function getRouter(): SimulcastRouter
    {
        return $this->router;
    }

    public function addPeer(PeerConnection $peer): self
    {
        if (count($this->peers) >= $this->maxPeers) {
            throw new MediaException("Room {$this->id} has reached maximum capacity ({$this->maxPeers} peers).");
        }

        $this->peers[$peer->getId()] = $peer;
        return $this;
    }

    public function removePeer(string $peerId): self
    {
        unset($this->peers[$peerId]);
        return $this;
    }

    public function hasPeer(string $peerId): bool
    {
        return isset($this->peers[$peerId]);
    }

    public function getPeer(string $peerId): PeerConnection
    {
        if (!isset($this->peers[$peerId])) {
            throw new PeerNotFoundException("Peer {$peerId} not found in room {$this->id}.");
        }

        return $this->peers[$peerId];
    }

    /**
     * @return array<string, PeerConnection>
     */
    public function getPeers(): array
    {
        return $this->peers;
    }

    /**
     * Broadcast an incoming RTP packet from a publisher track to all subscribed peers in the room.
     * Respects each subscriber's chosen Simulcast layer (High/Medium/Low).
     *
     * @return int Number of peers to which the packet was forwarded
     */
    public function broadcastPacket(string $publisherId, string $trackId, RtpPacket $packet): int
    {
        $publisher = $this->getPeer($publisherId);
        $track = $publisher->getTrack($trackId);

        if ($track === null || !$track->isEnabled()) {
            return 0;
        }

        $forwarded = 0;

        foreach ($this->peers as $peer) {
            // Do not echo back to publisher
            if ($peer->getId() === $publisherId) {
                continue;
            }

            if ($peer->isSubscribedTo($trackId)) {
                $layer = $peer->getSubscribedLayer($trackId);

                if ($this->router->shouldForward($track, $packet, $layer)) {
                    $routed = $this->router->routePacket($track, $packet, $peer, $layer);
                    $peer->deliver($routed, $trackId);
                    $forwarded++;
                    $this->forwardedPacketsCount++;
                }
            }
        }

        return $forwarded;
    }

    /**
     * Detect current active speaker based on Voice Activity Detection (VAD) audio level.
     * Returns null if all participants are silent (level <= -60 dBov) or muted.
     */
    public function getActiveSpeaker(): ?PeerConnection
    {
        $activePeer = null;
        $maxLevel = -127;

        foreach ($this->peers as $peer) {
            if ($peer->isMuted()) {
                continue;
            }

            $level = $peer->getAudioLevel();
            if ($level > -60 && $level > $maxLevel) {
                $maxLevel = $level;
                $activePeer = $peer;
            }
        }

        return $activePeer;
    }

    /**
     * Rank speakers by audio energy level.
     *
     * @return array<int, array{peerId: string, name: string, level: int}>
     */
    public function getRankedSpeakers(int $limit = 5): array
    {
        $list = [];
        foreach ($this->peers as $peer) {
            if (!$peer->isMuted()) {
                $list[] = [
                    'peerId' => $peer->getId(),
                    'name' => $peer->getName(),
                    'level' => $peer->getAudioLevel(),
                ];
            }
        }

        usort($list, fn(array $a, array $b) => $b['level'] <=> $a['level']);

        return array_slice($list, 0, $limit);
    }

    /**
     * Send Picture Loss Indication (PLI) request for a keyframe on video track.
     */
    public function requestKeyframe(string $publisherId, string $trackId): void
    {
        if (!isset($this->peers[$publisherId])) {
            return;
        }

        $track = $this->peers[$publisherId]->getTrack($trackId);
        if ($track === null) {
            return;
        }

        $pli = RtcpPacket::createPli($track->getSsrc(), 0);

        if ($this->keyframeCallback !== null) {
            ($this->keyframeCallback)($publisherId, $trackId, $pli);
        }
    }

    public function onKeyframeRequest(callable $callback): self
    {
        $this->keyframeCallback = $callback;
        return $this;
    }

    /**
     * Get real-time room statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $totalTracks = 0;
        foreach ($this->peers as $peer) {
            $totalTracks += count($peer->getPublishedTracks());
        }

        $activeSpeaker = $this->getActiveSpeaker();

        return [
            'roomId' => $this->id,
            'name' => $this->name,
            'peersCount' => count($this->peers),
            'maxPeers' => $this->maxPeers,
            'publishedTracksCount' => $totalTracks,
            'forwardedPackets' => $this->forwardedPacketsCount,
            'activeSpeaker' => $activeSpeaker ? [
                'id' => $activeSpeaker->getId(),
                'name' => $activeSpeaker->getName(),
                'level' => $activeSpeaker->getAudioLevel(),
            ] : null,
        ];
    }
}
