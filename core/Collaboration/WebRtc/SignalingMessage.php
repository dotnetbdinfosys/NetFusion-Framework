<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Collaboration\WebRtc;

use JsonSerializable;

class SignalingMessage implements JsonSerializable
{
    public const TYPE_JOIN = 'join-room';
    public const TYPE_LEAVE = 'leave-room';
    public const TYPE_OFFER = 'offer';
    public const TYPE_ANSWER = 'answer';
    public const TYPE_ICE_CANDIDATE = 'ice-candidate';
    public const TYPE_PEERS = 'room-peers';
    public const TYPE_ERROR = 'error';

    public function __construct(
        public readonly string $type,
        public readonly string $roomId,
        public readonly string $senderId,
        public readonly ?string $targetId = null,
        public readonly ?array $sdp = null,
        public readonly ?array $candidate = null,
        public readonly array $payload = [],
        public readonly float $timestamp = 0.0
    ) {
    }

    public static function create(
        string $type,
        string $roomId,
        string $senderId,
        ?string $targetId = null,
        ?array $sdp = null,
        ?array $candidate = null,
        array $payload = []
    ): self {
        return new self(
            type: $type,
            roomId: $roomId,
            senderId: $senderId,
            targetId: $targetId,
            sdp: $sdp,
            candidate: $candidate,
            payload: $payload,
            timestamp: microtime(true)
        );
    }

    public static function offer(string $roomId, string $senderId, string $targetId, array $sdp): self
    {
        return self::create(self::TYPE_OFFER, $roomId, $senderId, $targetId, sdp: $sdp);
    }

    public static function answer(string $roomId, string $senderId, string $targetId, array $sdp): self
    {
        return self::create(self::TYPE_ANSWER, $roomId, $senderId, $targetId, sdp: $sdp);
    }

    public static function iceCandidate(string $roomId, string $senderId, string $targetId, array $candidate): self
    {
        return self::create(self::TYPE_ICE_CANDIDATE, $roomId, $senderId, $targetId, candidate: $candidate);
    }

    public static function peers(string $roomId, array $peerIds): self
    {
        return self::create(self::TYPE_PEERS, $roomId, 'system', payload: ['peers' => $peerIds]);
    }

    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'room_id' => $this->roomId,
            'sender_id' => $this->senderId,
            'target_id' => $this->targetId,
            'sdp' => $this->sdp,
            'candidate' => $this->candidate,
            'payload' => $this->payload,
            'timestamp' => $this->timestamp,
        ], fn($v) => $v !== null && $v !== []);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type: (string)($data['type'] ?? ''),
            roomId: (string)($data['room_id'] ?? ''),
            senderId: (string)($data['sender_id'] ?? ''),
            targetId: isset($data['target_id']) ? (string)$data['target_id'] : null,
            sdp: isset($data['sdp']) ? (array)$data['sdp'] : null,
            candidate: isset($data['candidate']) ? (array)$data['candidate'] : null,
            payload: (array)($data['payload'] ?? []),
            timestamp: (float)($data['timestamp'] ?? microtime(true))
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
