<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/SocketPushCommand.php
| Module    : Console
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Push a real-time event to a WebSocket channel from CLI.
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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Sockets\SocketManager;

class SocketPushCommand extends Command
{
    protected string $name = 'socket:push';
    protected string $description = 'Push an event and payload to a real-time WebSocket channel.';

    public function handle(): int
    {
        $channel = $this->argument(0);
        $event = $this->argument(1) ?? 'message';
        $rawData = $this->argument(2);

        if (!$channel) {
            $this->error('Error: Target channel name is required.');
            $this->line('Usage: php netfusion socket:push <channel> <event> [json_payload]');
            return 1;
        }

        $data = [];
        if ($rawData) {
            $decoded = json_decode((string) $rawData, true);
            $data = is_array($decoded) ? $decoded : ['message' => $rawData];
        }

        /** @var SocketManager $manager */
        $manager = $this->container->get('socket');
        $success = $manager->push((string) $channel, (string) $event, $data);

        if ($success) {
            $this->info("✔ Event '{$event}' successfully dispatched to channel '{$channel}'.");
            return 0;
        }

        $this->error("✖ Failed to push event to channel '{$channel}'. Ensure 'php netfusion socket:serve' daemon is running.");
        return 1;
    }
}
