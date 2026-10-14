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
use NetFusion\Core\Media\Rtp\RtpPacket;

/**
 * SFU Peer Participant Connection.
 * Maintains published tracks, layer subscriptions, and Voice Activity Detection (VAD) audio levels.
 */
class PeerConnection
{
    protected string $id;
    protected string $name;
    /** @var array<string, MediaTrack> [trackId => MediaTrack] */
    protected array $publishedTracks = [];
    /** @var array<string, SimulcastLayer> [trackId => desiredLayer] */
    protected array $subscriptions = [];
    protected int $audioLevel = -127; // dBov (-127 = silence, 0 = max energy)
    protected bool $muted = false;
    /** @var callable|null function(RtpPacket $packet, string $trackId): void */
    protected $packetSink = null;

    public function __construct(string $id, string $name = 'Anonymous')
    {
        $this->id = $id;
        $this->name = $name;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function publishTrack(MediaTrack $track): self
    {
        $this->publishedTracks[$track->getId()] = $track;
        return $this;
    }

    public function unpublishTrack(string $trackId): self
    {
        unset($this->publishedTracks[$trackId]);
        return $this;
    }

    public function getTrack(string $trackId): ?MediaTrack
    {
        return $this->publishedTracks[$trackId] ?? null;
    }

    /**
     * @return array<string, MediaTrack>
     */
    public function getPublishedTracks(): array
    {
        return $this->publishedTracks;
    }

    public function subscribe(string $trackId, SimulcastLayer $layer = SimulcastLayer::HIGH): self
    {
        $this->subscriptions[$trackId] = $layer;
        return $this;
    }

    public function unsubscribe(string $trackId): self
    {
        unset($this->subscriptions[$trackId]);
        return $this;
    }

    public function isSubscribedTo(string $trackId): bool
    {
        return isset($this->subscriptions[$trackId]);
    }

    public function getSubscribedLayer(string $trackId): SimulcastLayer
    {
        return $this->subscriptions[$trackId] ?? SimulcastLayer::HIGH;
    }

    public function setAudioLevel(int $level): self
    {
        $this->audioLevel = max(-127, min(0, $level));
        return $this;
    }

    public function getAudioLevel(): int
    {
        return $this->audioLevel;
    }

    public function isMuted(): bool
    {
        return $this->muted;
    }

    public function setMuted(bool $muted): self
    {
        $this->muted = $muted;
        return $this;
    }

    public function setPacketSink(callable $sink): self
    {
        $this->packetSink = $sink;
        return $this;
    }

    /**
     * Deliver an incoming routed RTP packet to this peer.
     */
    public function deliver(RtpPacket $packet, string $trackId): void
    {
        if ($this->packetSink !== null) {
            ($this->packetSink)($packet, $trackId);
        }
    }
}
