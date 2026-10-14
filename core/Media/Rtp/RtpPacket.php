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

/**
 * RFC 3550 Real-time Transport Protocol (RTP) Packet.
 * High-performance binary serialization and parsing for WebRTC audio/video packets.
 */
class RtpPacket
{
    public const VERSION = 2;

    protected int $version = self::VERSION;
    protected bool $padding = false;
    protected bool $extension = false;
    protected int $csrcCount = 0;
    protected bool $marker = false;
    protected int $payloadType;
    protected int $sequenceNumber;
    protected int $timestamp;
    protected int $ssrc;
    /** @var array<int> */
    protected array $csrcList = [];
    protected ?string $extensionHeader = null;
    protected string $payload;

    /**
     * @param array<int> $csrcList
     */
    public function __construct(
        int $payloadType,
        int $sequenceNumber,
        int $timestamp,
        int $ssrc,
        string $payload = '',
        bool $marker = false,
        array $csrcList = [],
        ?string $extensionHeader = null,
        bool $padding = false
    ) {
        $this->payloadType = $payloadType & 0x7F;
        $this->sequenceNumber = $sequenceNumber & 0xFFFF;
        $this->timestamp = $timestamp & 0xFFFFFFFF;
        $this->ssrc = $ssrc & 0xFFFFFFFF;
        $this->payload = $payload;
        $this->marker = $marker;
        $this->csrcList = $csrcList;
        $this->csrcCount = count($csrcList) & 0x0F;
        $this->extensionHeader = $extensionHeader;
        $this->extension = $extensionHeader !== null;
        $this->padding = $padding;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function isPadding(): bool
    {
        return $this->padding;
    }

    public function hasExtension(): bool
    {
        return $this->extension;
    }

    public function getCsrcCount(): int
    {
        return $this->csrcCount;
    }

    public function isMarker(): bool
    {
        return $this->marker;
    }

    public function setMarker(bool $marker): self
    {
        $this->marker = $marker;
        return $this;
    }

    public function getPayloadType(): int
    {
        return $this->payloadType;
    }

    public function setPayloadType(int $payloadType): self
    {
        $this->payloadType = $payloadType & 0x7F;
        return $this;
    }

    public function getSequenceNumber(): int
    {
        return $this->sequenceNumber;
    }

    public function setSequenceNumber(int $sequenceNumber): self
    {
        $this->sequenceNumber = $sequenceNumber & 0xFFFF;
        return $this;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function setTimestamp(int $timestamp): self
    {
        $this->timestamp = $timestamp & 0xFFFFFFFF;
        return $this;
    }

    public function getSsrc(): int
    {
        return $this->ssrc;
    }

    public function setSsrc(int $ssrc): self
    {
        $this->ssrc = $ssrc & 0xFFFFFFFF;
        return $this;
    }

    /**
     * @return array<int>
     */
    public function getCsrcList(): array
    {
        return $this->csrcList;
    }

    public function getExtensionHeader(): ?string
    {
        return $this->extensionHeader;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function getPayloadLength(): int
    {
        return strlen($this->payload);
    }

    /**
     * Serialize RTP packet into RFC 3550 binary wire format.
     */
    public function serialize(): string
    {
        $b0 = (($this->version & 0x03) << 6)
            | (($this->padding ? 1 : 0) << 5)
            | (($this->extension ? 1 : 0) << 4)
            | ($this->csrcCount & 0x0F);

        $b1 = (($this->marker ? 1 : 0) << 7)
            | ($this->payloadType & 0x7F);

        // 12-byte base header: CC(n), SS(n), TT(N), SSRC(N)
        $binary = pack('CCnNN', $b0, $b1, $this->sequenceNumber, $this->timestamp, $this->ssrc);

        // CSRC list
        foreach ($this->csrcList as $csrc) {
            $binary .= pack('N', $csrc);
        }

        // Extension header
        if ($this->extension && $this->extensionHeader !== null) {
            $binary .= $this->extensionHeader;
        }

        // Payload
        $binary .= $this->payload;

        return $binary;
    }

    /**
     * Alias for serialize().
     */
    public function toBinary(): string
    {
        return $this->serialize();
    }

    /**
     * Parse raw binary buffer into RtpPacket instance.
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $binary): self
    {
        $len = strlen($binary);
        if ($len < 12) {
            throw new InvalidArgumentException(sprintf("RTP packet too short: %d bytes (minimum 12 bytes)", $len));
        }

        $header = unpack('Cbyte0/Cbyte1/nseq/Ntimestamp/Nssrc', substr($binary, 0, 12));
        if ($header === false) {
            throw new InvalidArgumentException("Failed to unpack RTP base header.");
        }

        $b0 = $header['byte0'];
        $b1 = $header['byte1'];

        $version = ($b0 >> 6) & 0x03;
        if ($version !== self::VERSION) {
            throw new InvalidArgumentException(sprintf("Unsupported RTP version: %d (expected 2)", $version));
        }

        $padding = (bool)(($b0 >> 5) & 0x01);
        $extension = (bool)(($b0 >> 4) & 0x01);
        $csrcCount = $b0 & 0x0F;

        $marker = (bool)(($b1 >> 7) & 0x01);
        $payloadType = $b1 & 0x7F;
        $sequenceNumber = $header['seq'];
        $timestamp = $header['timestamp'];
        $ssrc = $header['ssrc'];

        $offset = 12;

        // Parse CSRC
        $csrcList = [];
        for ($i = 0; $i < $csrcCount; $i++) {
            if ($offset + 4 > $len) {
                throw new InvalidArgumentException("Malformed RTP packet: truncated CSRC list.");
            }
            $csrc = unpack('N', substr($binary, $offset, 4))[1];
            $csrcList[] = $csrc;
            $offset += 4;
        }

        // Parse Header Extension if present
        $extHeader = null;
        if ($extension) {
            if ($offset + 4 > $len) {
                throw new InvalidArgumentException("Malformed RTP packet: truncated extension header.");
            }
            $extMeta = unpack('nprofile/nlength', substr($binary, $offset, 4));
            $extLenBytes = 4 + ($extMeta['length'] * 4);
            if ($offset + $extLenBytes > $len) {
                throw new InvalidArgumentException("Malformed RTP packet: extension payload exceeds packet boundary.");
            }
            $extHeader = substr($binary, $offset, $extLenBytes);
            $offset += $extLenBytes;
        }

        // Padding handling
        $paddingBytes = 0;
        if ($padding && $len > $offset) {
            $paddingBytes = ord($binary[$len - 1]);
        }

        $payloadLength = max(0, $len - $offset - $paddingBytes);
        $payload = substr($binary, $offset, $payloadLength);

        return new self(
            $payloadType,
            $sequenceNumber,
            $timestamp,
            $ssrc,
            $payload,
            $marker,
            $csrcList,
            $extHeader,
            $padding
        );
    }
}
