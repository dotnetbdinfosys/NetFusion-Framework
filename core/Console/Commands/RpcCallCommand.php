<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/RpcCallCommand.php
| Module    : Console
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI Command to invoke remote microservice RPC method directly from terminal.
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
use Throwable;

class RpcCallCommand extends Command
{
    protected string $name = 'rpc:call';
    protected string $description = 'Invoke an RPC microservice method and print JSON response';

    public function handle(): int
    {
        $service = (string) $this->argument(0);
        $method = (string) $this->argument(1);

        if (empty($service) || empty($method)) {
            $this->error("Usage: php netfusion rpc:call <service> <method> [json_params] [--host=...] [--port=...]");
            return 1;
        }

        $rawParams = $this->argument(2);
        $params = [];
        if ($rawParams !== null) {
            $decoded = json_decode((string) $rawParams, true);
            if (is_array($decoded)) {
                $params = $decoded;
            } else {
                $this->error("Invalid JSON parameters provided: {$rawParams}");
                return 1;
            }
        }

        $options = [];
        if ($this->option('host') !== null) {
            $options['host'] = (string) $this->option('host');
        }
        if ($this->option('port') !== null) {
            $options['port'] = (int) $this->option('port');
        }

        /** @var RpcManager $manager */
        $manager = $this->app->make(RpcManager::class);

        $this->info("Calling [{$service}::{$method}]...");

        try {
            $result = $manager->call($service, $method, $params, $options);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return 0;
        } catch (Throwable $e) {
            $this->error("RPC Call Failed: " . $e->getMessage());
            return 1;
        }
    }
}
