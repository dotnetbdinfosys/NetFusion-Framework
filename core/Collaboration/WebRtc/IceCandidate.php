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
 * Class IceCandidate
 *
 * Encapsulates an Interactive Connectivity Establishment (ICE) candidate for WebRTC NAT traversal.
 */
class IceCandidate
{
    public function __construct(
        public readonly string $candidate,
        public readonly ?string $sdpMid = null,
        public readonly ?int $sdpMLineIndex = null,
        public readonly string $fromPeer = '',
        public readonly ?string $toPeer = null,
        public readonly float $timestamp = 0.0
    ) {
    }

    public function toArray(): array
    {
        return [
            'candidate' => $this->candidate,
            'sdpMid' => $this->sdpMid,
            'sdpMLineIndex' => $this->sdpMLineIndex,
            'fromPeer' => $this->fromPeer,
            'toPeer' => $this->toPeer,
            'timestamp' => $this->timestamp > 0.0 ? $this->timestamp : microtime(true),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['candidate'] ?? ''),
            isset($data['sdpMid']) ? (string) $data['sdpMid'] : null,
            isset($data['sdpMLineIndex']) ? (int) $data['sdpMLineIndex'] : null,
            (string) ($data['fromPeer'] ?? ''),
            isset($data['toPeer']) ? (string) $data['toPeer'] : null,
            (float) ($data['timestamp'] ?? microtime(true))
        );
    }
}
