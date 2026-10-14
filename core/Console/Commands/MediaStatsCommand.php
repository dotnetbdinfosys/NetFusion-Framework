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
use NetFusion\Core\Media\MediaServerManager;

/**
 * CLI command to inspect WebRTC Media Server global telemetry and room stats.
 * Usage: php netfusion media:stats [--room=room1]
 */
class MediaStatsCommand extends Command
{
    protected string $name = 'media:stats';
    protected string $description = 'Display WebRTC Media Server telemetry, bandwidth, and room statistics';

    public function handle(): int
    {
        /** @var MediaServerManager $manager */
        $manager = function_exists('app') && app()->has('media') ? app('media') : new MediaServerManager();

        $roomId = $this->option('room');

        if ($roomId !== null) {
            if (!$manager->hasRoom($roomId)) {
                $this->error("Room '{$roomId}' not found.");
                return 1;
            }

            $room = $manager->getRoom($roomId);
            $stats = $room->getStats();

            $this->info("=================================================");
            $this->info("       WebRTC Room Telemetry: " . $room->getId());
            $this->info("=================================================");
            $this->line("Room Name        : " . $stats['name']);
            $this->line("Active Peers     : " . $stats['peersCount'] . " / " . $stats['maxPeers']);
            $this->line("Published Tracks : " . $stats['publishedTracksCount']);
            $this->line("Forwarded Packets: " . $stats['forwardedPackets']);
            if ($stats['activeSpeaker']) {
                $this->line(sprintf("Active Speaker   : %s (%s dBov)", $stats['activeSpeaker']['name'], $stats['activeSpeaker']['level']));
            } else {
                $this->line("Active Speaker   : None (Silence / Inactive)");
            }
            $this->info("=================================================");
            return 0;
        }

        $global = $manager->getGlobalStats();

        $this->info("=================================================");
        $this->info("     NetFusion WebRTC Media Server Global Stats  ");
        $this->info("=================================================");
        $this->line("Active SFU Rooms : " . $global['totalRooms']);
        $this->line("Connected Peers  : " . $global['totalPeers']);
        $this->line("Active Tracks    : " . $global['totalTracks']);
        $this->line("Packets Routed   : " . $global['totalForwardedPackets']);
        $this->line("Default Video    : " . $global['defaultVideoCodec']);
        $this->line("Default Audio    : " . $global['defaultAudioCodec']);
        $this->info("=================================================");

        return 0;
    }
}
