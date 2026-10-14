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
 * Class SdpMessage
 *
 * Encapsulates a WebRTC Session Description Protocol (SDP) Offer or Answer.
 */
class SdpMessage
{
    public function __construct(
        public readonly string $type, // 'offer' or 'answer'
        public readonly string $sdp,
        public readonly string $fromPeer,
        public readonly ?string $toPeer = null,
        public readonly float $timestamp = 0.0
    ) {
    }

    public function isOffer(): bool
    {
        return strtolower($this->type) === 'offer';
    }

    public function isAnswer(): bool
    {
        return strtolower($this->type) === 'answer';
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'sdp' => $this->sdp,
            'fromPeer' => $this->fromPeer,
            'toPeer' => $this->toPeer,
            'timestamp' => $this->timestamp > 0.0 ? $this->timestamp : microtime(true),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['type'] ?? 'offer'),
            (string) ($data['sdp'] ?? ''),
            (string) ($data['fromPeer'] ?? ''),
            isset($data['toPeer']) ? (string) $data['toPeer'] : null,
            (float) ($data['timestamp'] ?? microtime(true))
        );
    }
}
