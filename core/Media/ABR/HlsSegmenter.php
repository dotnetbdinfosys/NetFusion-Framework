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
 * HTTP Live Streaming (HLS) RFC 8216 Segmenter & Playlist Generator.
 * Handles Master Multi-Bitrate ABR Playlists and Media Segment Playlists.
 */
class HlsSegmenter
{
    /** @var array<int, array{name: string, bandwidth: int, resolution: string, uri: string, codecs: string}> */
    protected array $variants = [];

    /** @var array<int, array{duration: float, uri: string, title: string}> */
    protected array $segments = [];

    protected float $targetDuration = 6.0;
    protected int $version = 3;

    public function __construct(float $targetDuration = 6.0, int $version = 3)
    {
        $this->targetDuration = $targetDuration;
        $this->version = $version;
    }

    /**
     * Add a variant stream profile for Master Playlist.
     */
    public function addVariant(
        string $name,
        int $bandwidth,
        string $resolution,
        string $uri,
        string $codecs = 'avc1.4d401f,mp4a.40.2'
    ): self {
        $this->variants[] = [
            'name' => $name,
            'bandwidth' => $bandwidth,
            'resolution' => $resolution,
            'uri' => $uri,
            'codecs' => $codecs,
        ];
        return $this;
    }

    /**
     * Add a media segment.
     */
    public function addSegment(float $duration, string $uri, string $title = ''): self
    {
        $this->segments[] = [
            'duration' => $duration,
            'uri' => $uri,
            'title' => $title,
        ];
        return $this;
    }

    /**
     * @return array<int, array{duration: float, uri: string, title: string}>
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    /**
     * @return array<int, array{name: string, bandwidth: int, resolution: string, uri: string, codecs: string}>
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    /**
     * Generate HLS Master Playlist (m3u8).
     */
    public function generateMasterPlaylist(): string
    {
        $lines = [
            '#EXTM3U',
            '#EXT-X-VERSION:' . $this->version,
        ];

        // Sort variants by bandwidth descending
        $sorted = $this->variants;
        usort($sorted, fn($a, $b) => $b['bandwidth'] <=> $a['bandwidth']);

        foreach ($sorted as $v) {
            $lines[] = sprintf(
                '#EXT-X-STREAM-INF:BANDWIDTH=%d,RESOLUTION=%s,CODECS="%s",NAME="%s"',
                $v['bandwidth'],
                $v['resolution'],
                $v['codecs'],
                $v['name']
            );
            $lines[] = $v['uri'];
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Generate HLS Media Playlist (m3u8) for individual variant or stream.
     *
     * @param array<int, array{duration: float, uri: string, title?: string}>|null $segments
     */
    public function generateMediaPlaylist(?array $segments = null, bool $isVod = true, int $mediaSequence = 0): string
    {
        $targetSegments = $segments ?? $this->segments;
        $maxDuration = $this->targetDuration;

        foreach ($targetSegments as $s) {
            if ($s['duration'] > $maxDuration) {
                $maxDuration = ceil($s['duration']);
            }
        }

        $lines = [
            '#EXTM3U',
            '#EXT-X-VERSION:' . $this->version,
            '#EXT-X-TARGETDURATION:' . (int) ceil($maxDuration),
            '#EXT-X-MEDIA-SEQUENCE:' . $mediaSequence,
        ];

        foreach ($targetSegments as $s) {
            $lines[] = sprintf('#EXTINF:%.6f,%s', $s['duration'], $s['title'] ?? '');
            $lines[] = $s['uri'];
        }

        if ($isVod) {
            $lines[] = '#EXT-X-ENDLIST';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Helper to create standard predefined multi-bitrate profiles.
     */
    public static function createStandardProfiles(string $baseUri = ''): self
    {
        $prefix = rtrim($baseUri, '/');
        $prefix = $prefix !== '' ? $prefix . '/' : '';

        return (new self())
            ->addVariant('1080p', 4500000, '1920x1080', "{$prefix}1080p/index.m3u8")
            ->addVariant('720p', 2200000, '1280x720', "{$prefix}720p/index.m3u8")
            ->addVariant('480p', 800000, '854x480', "{$prefix}480p/index.m3u8")
            ->addVariant('360p', 400000, '640x360', "{$prefix}360p/index.m3u8");
    }
}
