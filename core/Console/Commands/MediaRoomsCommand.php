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
 * CLI command to manage WebRTC SFU Conference Rooms.
 * Usage: php netfusion media:rooms [--create=room1] [--name="Standup"] [--delete=room1]
 */
class MediaRoomsCommand extends Command
{
    protected string $name = 'media:rooms';
    protected string $description = 'List, create, or delete WebRTC SFU conference rooms';

    public function handle(): int
    {
        /** @var MediaServerManager $manager */
        $manager = function_exists('app') && app()->has('media') ? app('media') : new MediaServerManager();

        $createId = $this->option('create');
        $name = $this->option('name') ?? 'Conference';
        $deleteId = $this->option('delete');

        if ($createId !== null) {
            $room = $manager->createRoom($createId, $name);
            $this->info("SFU Room [{$room->getId()}] '{$room->getName()}' created successfully.");
            return 0;
        }

        if ($deleteId !== null) {
            if ($manager->deleteRoom($deleteId)) {
                $this->info("SFU Room [{$deleteId}] deleted successfully.");
            } else {
                $this->error("SFU Room [{$deleteId}] not found.");
            }
            return 0;
        }

        $rooms = $manager->getRooms();

        $this->info("=================================================");
        $this->info("       NetFusion WebRTC SFU Active Rooms         ");
        $this->info("=================================================");
        $this->line("Total Rooms: " . count($rooms));

        if (empty($rooms)) {
            $this->line("No active conference rooms.");
        } else {
            foreach ($rooms as $room) {
                $stats = $room->getStats();
                $this->line(sprintf(
                    "- [%s] %s | Peers: %d/%d | Tracks: %d | Packets: %d",
                    $room->getId(),
                    $room->getName(),
                    $stats['peersCount'],
                    $stats['maxPeers'],
                    $stats['publishedTracksCount'],
                    $stats['forwardedPackets']
                ));
            }
        }
        $this->info("=================================================");

        return 0;
    }
}
