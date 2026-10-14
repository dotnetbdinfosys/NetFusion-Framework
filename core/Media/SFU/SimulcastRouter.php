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
 * Simulcast Layer Router & Forwarder.
 * Matches incoming RTP packets with subscriber's desired layer and performs
 * dynamic layer switching and SSRC translation.
 */
class SimulcastRouter
{
    /** @var array<string, int> Track-Subscriber key => last forwarded sequence number */
    protected array $seqCounters = [];

    /**
     * Determine if an incoming packet for a track matches the target subscriber layer.
     */
    public function shouldForward(MediaTrack $track, RtpPacket $packet, SimulcastLayer $targetLayer): bool
    {
        if (!$track->isEnabled()) {
            return false;
        }

        if (!$track->isSimulcast()) {
            return true;
        }

        $expectedSsrc = $track->getSsrcForLayer($targetLayer);
        return $packet->getSsrc() === $expectedSsrc;
    }

    /**
     * Rewrite packet for subscriber if SSRC or sequence translation is needed.
     */
    public function routePacket(
        MediaTrack $track,
        RtpPacket $packet,
        PeerConnection $subscriber,
        SimulcastLayer $layer
    ): RtpPacket {
        $key = $subscriber->getId() . ':' . $track->getId();

        if (!isset($this->seqCounters[$key])) {
            $this->seqCounters[$key] = $packet->getSequenceNumber();
        } else {
            $this->seqCounters[$key] = ($this->seqCounters[$key] + 1) & 0xFFFF;
        }

        // Return a packet copy with track's base SSRC and continuous sequence number
        return new RtpPacket(
            payloadType: $packet->getPayloadType(),
            sequenceNumber: $this->seqCounters[$key],
            timestamp: $packet->getTimestamp(),
            ssrc: $track->getSsrc(),
            payload: $packet->getPayload(),
            marker: $packet->isMarker(),
            csrcList: $packet->getCsrcList()
        );
    }

    /**
     * Choose optimal Simulcast layer based on available subscriber bandwidth.
     */
    public function selectLayerForBandwidth(int $bandwidthKbps): SimulcastLayer
    {
        if ($bandwidthKbps >= 1500) {
            return SimulcastLayer::HIGH;
        }

        if ($bandwidthKbps >= 500) {
            return SimulcastLayer::MEDIUM;
        }

        return SimulcastLayer::LOW;
    }

    /**
     * Reset sequence tracking for a subscriber track.
     */
    public function resetSubscriber(string $subscriberId, string $trackId): void
    {
        unset($this->seqCounters[$subscriberId . ':' . $trackId]);
    }
}
