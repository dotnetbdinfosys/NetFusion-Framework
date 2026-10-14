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

namespace NetFusion\Core\Media\Rtp;

use InvalidArgumentException;
use NetFusion\Core\Media\Enums\RtcpType;

/**
 * RFC 3550 / RFC 4585 Real-time Transport Control Protocol (RTCP) Packet.
 * Implements Sender Report (SR), Receiver Report (RR), NACK, PLI (Keyframe request), and REMB.
 */
class RtcpPacket
{
    public const VERSION = 2;

    protected int $version = self::VERSION;
    protected bool $padding = false;
    protected int $countOrFmt = 0; // Reception report count (RC) or Feedback message type (FMT)
    protected RtcpType $type;
    protected int $senderSsrc;
    protected ?int $mediaSsrc = null;
    protected string $payload;

    public function __construct(
        RtcpType $type,
        int $senderSsrc,
        int $countOrFmt = 0,
        ?int $mediaSsrc = null,
        string $payload = '',
        bool $padding = false
    ) {
        $this->type = $type;
        $this->senderSsrc = $senderSsrc & 0xFFFFFFFF;
        $this->countOrFmt = $countOrFmt & 0x1F;
        $this->mediaSsrc = $mediaSsrc !== null ? ($mediaSsrc & 0xFFFFFFFF) : null;
        $this->payload = $payload;
        $this->padding = $padding;
    }

    /**
     * Create Picture Loss Indication (PLI) to request a video keyframe.
     */
    public static function createPli(int $senderSsrc, int $mediaSsrc): self
    {
        return new self(RtcpType::PSFB, $senderSsrc, 1, $mediaSsrc);
    }

    /**
     * Create Negative Acknowledgment (NACK) for lost sequence numbers.
     *
     * @param array<int> $lostSequenceNumbers
     */
    public static function createNack(int $senderSsrc, int $mediaSsrc, array $lostSequenceNumbers): self
    {
        $payload = '';
        foreach ($lostSequenceNumbers as $seq) {
            // Generic NACK FCI: PID (16-bit packet ID) + BLP (16-bit bitmask of lost packets)
            $payload .= pack('nn', $seq & 0xFFFF, 0);
        }

        return new self(RtcpType::RTPFB, $senderSsrc, 1, $mediaSsrc, $payload);
    }

    /**
     * Create Receiver Estimated Maximum Bitrate (REMB) feedback.
     *
     * @param array<int> $ssrcs
     */
    public static function createRemb(int $senderSsrc, array $ssrcs, int $bitrateBps): self
    {
        // REMB Payload: 'REMB' (4 bytes) + Num SSRC (1 byte) + BR Exp/Mantissa (3 bytes) + SSRCs (4*N bytes)
        $numSsrc = count($ssrcs) & 0xFF;

        // Mantissa (18-bit) and Exponent (6-bit)
        $exp = 0;
        $mantissa = $bitrateBps;
        while ($mantissa > 0x3FFFF && $exp < 63) {
            $mantissa >>= 1;
            $exp++;
        }
        $brField = (($exp & 0x3F) << 18) | ($mantissa & 0x3FFFF);

        // Note: $brField is 24-bit, we take lower 3 bytes
        $payload = 'REMB' . chr($numSsrc) . substr(pack('N', $brField), 1, 3);

        foreach ($ssrcs as $ssrc) {
            $payload .= pack('N', $ssrc & 0xFFFFFFFF);
        }

        return new self(RtcpType::PSFB, $senderSsrc, 15, 0, $payload);
    }

    public function getType(): RtcpType
    {
        return $this->type;
    }

    public function getSenderSsrc(): int
    {
        return $this->senderSsrc;
    }

    public function getMediaSsrc(): ?int
    {
        return $this->mediaSsrc;
    }

    public function getCountOrFmt(): int
    {
        return $this->countOrFmt;
    }

    public function getFeedbackFormat(): int
    {
        return $this->countOrFmt;
    }

    public function isPli(): bool
    {
        return $this->type === RtcpType::PSFB && $this->countOrFmt === 1;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * Serialize RTCP packet into 32-bit word aligned binary wire format.
     */
    public function serialize(): string
    {
        $body = pack('N', $this->senderSsrc);
        if ($this->type === RtcpType::RTPFB || $this->type === RtcpType::PSFB) {
            $body .= pack('N', $this->mediaSsrc ?? 0);
        } elseif ($this->mediaSsrc !== null) {
            $body .= pack('N', $this->mediaSsrc);
        }
        $body .= $this->payload;

        // 32-bit word alignment padding
        $padBytes = 0;
        $remainder = strlen($body) % 4;
        if ($remainder !== 0) {
            $padBytes = 4 - $remainder;
            $body .= str_repeat("\x00", $padBytes);
        }

        $lengthWords = (int)(strlen($body) / 4);

        $b0 = (($this->version & 0x03) << 6)
            | (($this->padding ? 1 : 0) << 5)
            | ($this->countOrFmt & 0x1F);

        $header = pack('CCn', $b0, $this->type->value, $lengthWords);

        return $header . $body;
    }

    /**
     * Alias for serialize().
     */
    public function toBinary(): string
    {
        return $this->serialize();
    }

    /**
     * Parse binary buffer into RtcpPacket.
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $binary): self
    {
        $len = strlen($binary);
        if ($len < 8) {
            throw new InvalidArgumentException(sprintf("RTCP packet too short: %d bytes (minimum 8 bytes)", $len));
        }

        $header = unpack('Cbyte0/Ctype/nlength', substr($binary, 0, 4));
        if ($header === false) {
            throw new InvalidArgumentException("Failed to unpack RTCP header.");
        }

        $b0 = $header['byte0'];
        $typeVal = $header['type'];
        $type = RtcpType::tryFrom($typeVal);
        if ($type === null) {
            throw new InvalidArgumentException(sprintf("Unsupported RTCP packet type: %d", $typeVal));
        }

        $countOrFmt = $b0 & 0x1F;
        $padding = (bool)(($b0 >> 5) & 0x01);

        $senderSsrc = unpack('N', substr($binary, 4, 4))[1];
        $offset = 8;
        $mediaSsrc = null;

        if ($type === RtcpType::RTPFB || $type === RtcpType::PSFB) {
            if ($len >= 12) {
                $mediaSsrc = unpack('N', substr($binary, 8, 4))[1];
                $offset = 12;
            }
        }

        $payload = substr($binary, $offset);

        return new self($type, $senderSsrc, $countOrFmt, $mediaSsrc, $payload, $padding);
    }
}
