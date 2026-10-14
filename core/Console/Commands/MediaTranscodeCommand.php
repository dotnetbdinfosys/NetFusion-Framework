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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Media\ABR\HlsSegmenter;

/**
 * CLI command to generate HLS Adaptive Bitrate (ABR) playlists and profile templates.
 * Usage: php netfusion media:transcode [--prefix=https://cdn.example.com/live/] [--out=master.m3u8]
 */
class MediaTranscodeCommand extends Command
{
    protected string $name = 'media:transcode';
    protected string $description = 'Generate HLS Adaptive Bitrate (ABR) Master & Media playlists';

    public function handle(): int
    {
        $prefix = $this->option('prefix') ?? '';
        $outFile = $this->option('out');

        $segmenter = HlsSegmenter::createStandardProfiles($prefix);
        $masterPlaylist = $segmenter->generateMasterPlaylist();

        if ($outFile !== null) {
            file_put_contents($outFile, $masterPlaylist);
            $this->info("HLS Master Playlist written to: {$outFile}");
        } else {
            $this->info("=================================================");
            $this->info("       Generated HLS ABR Master Playlist         ");
            $this->info("=================================================");
            $this->line(trim($masterPlaylist));
            $this->info("=================================================");
        }

        return 0;
    }
}
