<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/RpcServeCommand.php
| Module    : Console
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI Command to start RPC Server daemon on specified host and port.
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
use NetFusion\Core\RPC\RpcManager;

class RpcServeCommand extends Command
{
    protected string $name = 'rpc:serve';
    protected string $description = 'Start high-speed NetFusion RPC microservice daemon';

    public function handle(): int
    {
        $host = (string) ($this->option('host') ?? config('rpc.host', '0.0.0.0'));
        $port = (int) ($this->option('port') ?? config('rpc.port', 9500));

        $this->info("Starting NetFusion Microservice RPC Server...");
        $this->line("  Endpoint : tcp://{$host}:{$port}");
        $this->line("  Press Ctrl+C to gracefully stop.\n");

        /** @var RpcManager $manager */
        $manager = $this->app->make(RpcManager::class);
        $server = $manager->server();

        $server->serve($host, $port);

        return 0;
    }
}
