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

use NetFusion\Core\Media\Enums\MediaType;
use NetFusion\Core\Media\Enums\SimulcastLayer;

/**
 * WebRTC Media Track Descriptor (Audio or Video).
 * Supports single-stream and multi-stream Simulcast (High/Medium/Low spatial layers).
 */
class MediaTrack
{
    protected string $id;
    protected MediaType $kind;
    protected int $ssrc;
    protected int $payloadType;
    protected string $codec;
    /** @var array<string, int> Mapping of SimulcastLayer value => SSRC */
    protected array $simulcastSsrcs = [];
    protected int $bitrateKbps;
    protected bool $enabled = true;

    /**
     * @param array<string, int> $simulcastSsrcs e.g. ['high' => 10001, 'medium' => 10002, 'low' => 10003]
     */
    public function __construct(
        string $id,
        MediaType $kind,
        int $ssrc,
        int $payloadType = 96,
        string $codec = 'VP8',
        array $simulcastSsrcs = [],
        int $bitrateKbps = 1500
    ) {
        $this->id = $id;
        $this->kind = $kind;
        $this->ssrc = $ssrc;
        $this->payloadType = $payloadType;
        $this->codec = strtoupper($codec);
        $this->simulcastSsrcs = $simulcastSsrcs;
        $this->bitrateKbps = $bitrateKbps;

        // If no simulcast map, assign primary SSRC as HIGH
        if (empty($this->simulcastSsrcs)) {
            $this->simulcastSsrcs[SimulcastLayer::HIGH->value] = $this->ssrc;
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getKind(): MediaType
    {
        return $this->kind;
    }

    public function getSsrc(): int
    {
        return $this->ssrc;
    }

    public function getPayloadType(): int
    {
        return $this->payloadType;
    }

    public function getCodec(): string
    {
        return $this->codec;
    }

    /**
     * @return array<string, int>
     */
    public function getSimulcastSsrcs(): array
    {
        return $this->simulcastSsrcs;
    }

    public function getSsrcForLayer(SimulcastLayer $layer): int
    {
        return $this->simulcastSsrcs[$layer->value] ?? $this->ssrc;
    }

    public function isSimulcast(): bool
    {
        return count($this->simulcastSsrcs) > 1;
    }

    public function getBitrateKbps(): int
    {
        return $this->bitrateKbps;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }
}
