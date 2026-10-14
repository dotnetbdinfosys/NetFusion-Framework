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

namespace NetFusion\Core\Media\ABR;

/**
 * H.264 / AVC Network Abstraction Layer (NAL) Unit.
 * Parses NAL header, reference indicator, unit types (IDR, non-IDR, SPS, PPS),
 * and handles Annex B byte-stream formatting.
 */
class NalUnit
{
    public const TYPE_NON_IDR = 1;
    public const TYPE_DATA_PARTITION_A = 2;
    public const TYPE_DATA_PARTITION_B = 3;
    public const TYPE_DATA_PARTITION_C = 4;
    public const TYPE_IDR = 5;
    public const TYPE_SEI = 6;
    public const TYPE_SPS = 7;
    public const TYPE_PPS = 8;
    public const TYPE_AUD = 9;
    public const TYPE_END_OF_SEQUENCE = 10;
    public const TYPE_END_OF_STREAM = 11;

    protected int $refIdc;
    protected int $type;
    protected string $payload;

    public function __construct(int $refIdc, int $type, string $payload)
    {
        $this->refIdc = $refIdc;
        $this->type = $type;
        $this->payload = $payload;
    }

    public function getRefIdc(): int
    {
        return $this->refIdc;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function isKeyframe(): bool
    {
        return $this->type === self::TYPE_IDR;
    }

    public function isParameterSet(): bool
    {
        return $this->type === self::TYPE_SPS || $this->type === self::TYPE_PPS;
    }

    public function getTypeName(): string
    {
        return match ($this->type) {
            self::TYPE_NON_IDR => 'NON_IDR_SLICE',
            self::TYPE_DATA_PARTITION_A => 'DATA_PARTITION_A',
            self::TYPE_DATA_PARTITION_B => 'DATA_PARTITION_B',
            self::TYPE_DATA_PARTITION_C => 'DATA_PARTITION_C',
            self::TYPE_IDR => 'IDR_KEYFRAME',
            self::TYPE_SEI => 'SEI',
            self::TYPE_SPS => 'SPS',
            self::TYPE_PPS => 'PPS',
            self::TYPE_AUD => 'AUD',
            self::TYPE_END_OF_SEQUENCE => 'END_OF_SEQUENCE',
            self::TYPE_END_OF_STREAM => 'END_OF_STREAM',
            default => "UNKNOWN_{$this->type}",
        };
    }

    /**
     * Parse raw NAL unit bytes (with 1-byte NAL header).
     */
    public static function parse(string $rawNal): self
    {
        if (strlen($rawNal) < 1) {
            return new self(0, 0, '');
        }

        $headerByte = ord($rawNal[0]);
        // Bit 7: forbidden_zero_bit (should be 0)
        // Bits 5-6: nal_ref_idc
        $refIdc = ($headerByte >> 5) & 0x03;
        // Bits 0-4: nal_unit_type
        $type = $headerByte & 0x1F;
        $payload = substr($rawNal, 1);

        return new self($refIdc, $type, $payload);
    }

    /**
     * Serialize to raw NAL unit including header byte.
     */
    public function toBinary(): string
    {
        $headerByte = (($this->refIdc & 0x03) << 5) | ($this->type & 0x1F);
        return chr($headerByte) . $this->payload;
    }

    /**
     * Serialize to Annex B format with 4-byte start code (0x00 00 00 01).
     */
    public function toAnnexB(): string
    {
        return "\x00\x00\x00\x01" . $this->toBinary();
    }

    /**
     * Parse Annex B byte stream (e.g. from H.264 file or network) into array of NalUnits.
     *
     * @return array<int, NalUnit>
     */
    public static function parseAnnexB(string $stream): array
    {
        $units = [];
        $len = strlen($stream);
        if ($len < 4) {
            return $units;
        }

        $startOffsets = [];
        $i = 0;
        while ($i < $len - 3) {
            if ($stream[$i] === "\x00" && $stream[$i + 1] === "\x00") {
                if ($stream[$i + 2] === "\x01") {
                    $startOffsets[] = ['start' => $i, 'prefixLen' => 3];
                    $i += 3;
                    continue;
                }
                if ($i < $len - 4 && $stream[$i + 2] === "\x00" && $stream[$i + 3] === "\x01") {
                    $startOffsets[] = ['start' => $i, 'prefixLen' => 4];
                    $i += 4;
                    continue;
                }
            }
            $i++;
        }

        for ($k = 0; $k < count($startOffsets); $k++) {
            $nalStart = $startOffsets[$k]['start'] + $startOffsets[$k]['prefixLen'];
            $nalEnd = ($k + 1 < count($startOffsets)) ? $startOffsets[$k + 1]['start'] : $len;

            if ($nalEnd > $nalStart) {
                $rawNal = substr($stream, $nalStart, $nalEnd - $nalStart);
                $units[] = self::parse($rawNal);
            }
        }

        return $units;
    }
}
