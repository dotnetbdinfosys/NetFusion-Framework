<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/SocketServeCommand.php
| Module    : Console
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Run the standalone NetFusion WebSocket & Push server daemon.
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
use NetFusion\Core\Sockets\WebSocketServer;

class SocketServeCommand extends Command
{
    protected string $name = 'socket:serve';
    protected string $description = 'Start the NetFusion high-performance standalone WebSocket server daemon.';

    public function handle(): int
    {
        $host = (string) ($this->option('host') ?? config('socket.host', '0.0.0.0'));
        $port = (int) ($this->option('port') ?? config('socket.port', 8080));
        $pushPort = (int) ($this->option('push-port') ?? config('socket.push_port', 8081));

        /** @var SocketManager $manager */
        $manager = $this->container->get('socket');
        $server = $manager->createServer($host, $port, $pushPort);

        $this->info("======================================================================");
        $this->info("  NetFusion WebSocket & Real-Time Channel Server Daemon");
        $this->info("======================================================================");
        $this->info("  WebSocket URI : ws://{$host}:{$port}");
        $this->info("  IPC Push URI  : tcp://127.0.0.1:{$pushPort}");
        $this->info("  Protocol      : RFC 6455 (Text, Binary, Ping/Pong, Presence Rooms)");
        $this->info("  Status        : Listening for incoming connections... (Ctrl+C to stop)\n");

        $server->on('open', function ($conn) {
            $this->info(sprintf("[%s] [CONNECT] Client connected: %s (IP: %s:%d)", date('Y-m-d H:i:s'), $conn->getId(), $conn->getRemoteAddress(), $conn->getRemotePort()));
        });

        $server->on('close', function ($conn, $code, $reason) {
            $this->info(sprintf("[%s] [DISCONNECT] Client disconnected: %s (Code: %d, Reason: %s)", date('Y-m-d H:i:s'), $conn->getId(), $code, $reason));
        });

        $server->listen();

        return 0;
    }
}
