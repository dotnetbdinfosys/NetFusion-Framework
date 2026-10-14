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

namespace NetFusion\Core\Media;

use NetFusion\Core\Media\ABR\CongestionController;
use NetFusion\Core\Media\ABR\HlsSegmenter;
use NetFusion\Core\Media\Exceptions\RoomNotFoundException;
use NetFusion\Core\Media\Rtp\JitterBuffer;
use NetFusion\Core\Media\Rtp\RtpPacket;
use NetFusion\Core\Media\Rtp\SrtpContext;
use NetFusion\Core\Media\SFU\PeerConnection;
use NetFusion\Core\Media\SFU\SfuRoom;

/**
 * Enterprise WebRTC Media Server & SFU Manager.
 * Orchestrates multi-party conference rooms, RTP/RTCP transport pipelines,
 * SRTP security contexts, and Adaptive Bitrate (ABR) pipelines.
 */
class MediaServerManager
{
    /** @var array<string, mixed> */
    protected array $config;

    /** @var array<string, SfuRoom> [roomId => SfuRoom] */
    protected array $rooms = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default_video_codec' => 'VP8',
            'default_audio_codec' => 'OPUS',
            'max_room_peers' => 100,
            'jitter_buffer_capacity' => 100,
            'enable_srtp' => true,
            'hls_target_duration' => 6.0,
            'recording_path' => 'storage/media/recordings',
        ], $config);
    }

    /**
     * Create or retrieve an SFU Conference Room.
     */
    public function createRoom(string $roomId, string $name = 'Conference', ?int $maxPeers = null): SfuRoom
    {
        $limit = $maxPeers ?? (int) $this->config['max_room_peers'];
        $room = new SfuRoom($roomId, $name, $limit);
        $this->rooms[$roomId] = $room;
        return $room;
    }

    public function getRoom(string $roomId): SfuRoom
    {
        if (!isset($this->rooms[$roomId])) {
            throw new RoomNotFoundException("Room '{$roomId}' does not exist on Media Server.");
        }

        return $this->rooms[$roomId];
    }

    public function hasRoom(string $roomId): bool
    {
        return isset($this->rooms[$roomId]);
    }

    public function deleteRoom(string $roomId): bool
    {
        if (isset($this->rooms[$roomId])) {
            unset($this->rooms[$roomId]);
            return true;
        }

        return false;
    }

    /**
     * @return array<string, SfuRoom>
     */
    public function getRooms(): array
    {
        return $this->rooms;
    }

    /**
     * Create a new JitterBuffer configured from media server defaults.
     */
    public function createJitterBuffer(?int $capacity = null): JitterBuffer
    {
        return new JitterBuffer($capacity ?? (int) $this->config['jitter_buffer_capacity']);
    }

    /**
     * Create an SRTP Context with master key and salt.
     */
    public function createSrtpContext(string $masterKey, string $masterSalt): SrtpContext
    {
        return new SrtpContext($masterKey, $masterSalt);
    }

    /**
     * Create a CongestionController instance.
     */
    public function createCongestionController(int $initialBwKbps = 1500): CongestionController
    {
        return new CongestionController($initialBwKbps);
    }

    /**
     * Create an HLS Segmenter with default settings.
     */
    public function createHlsSegmenter(?float $targetDuration = null): HlsSegmenter
    {
        return new HlsSegmenter($targetDuration ?? (float) $this->config['hls_target_duration']);
    }

    /**
     * Build an RTP packet quickly.
     */
    public function makeRtpPacket(
        int $payloadType,
        int $sequenceNumber,
        int $timestamp,
        int $ssrc,
        string $payload,
        bool $marker = false
    ): RtpPacket {
        return new RtpPacket(
            payloadType: $payloadType,
            sequenceNumber: $sequenceNumber,
            timestamp: $timestamp,
            ssrc: $ssrc,
            payload: $payload,
            marker: $marker
        );
    }

    /**
     * Get aggregate statistics across all rooms on the media server.
     *
     * @return array<string, mixed>
     */
    public function getGlobalStats(): array
    {
        $totalPeers = 0;
        $totalTracks = 0;
        $totalPackets = 0;

        foreach ($this->rooms as $room) {
            $stats = $room->getStats();
            $totalPeers += $stats['peersCount'];
            $totalTracks += $stats['publishedTracksCount'];
            $totalPackets += $stats['forwardedPackets'];
        }

        return [
            'totalRooms' => count($this->rooms),
            'totalPeers' => $totalPeers,
            'totalTracks' => $totalTracks,
            'totalForwardedPackets' => $totalPackets,
            'defaultVideoCodec' => $this->config['default_video_codec'],
            'defaultAudioCodec' => $this->config['default_audio_codec'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
