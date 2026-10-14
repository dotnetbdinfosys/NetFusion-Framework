<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/RpcRegistryCommand.php
| Module    : Console
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI Command to display active microservice nodes in the mesh registry.
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

class RpcRegistryCommand extends Command
{
    protected string $name = 'rpc:registry';
    protected string $description = 'List all registered service instances in the Service Mesh registry';

    public function handle(): int
    {
        /** @var RpcManager $manager */
        $manager = $this->app->make(RpcManager::class);
        $registry = $manager->registry();

        $services = $registry->all();

        if (empty($services)) {
            $this->warn("No active services currently registered in the Service Mesh.");
            return 0;
        }

        $this->info("Service Mesh Registry Nodes:");
        $this->line(str_repeat('-', 75));
        printf("%-20s %-22s %-20s %-8s\n", "SERVICE", "NODE ID", "ENDPOINT", "STATUS");
        $this->line(str_repeat('-', 75));

        foreach ($services as $serviceName => $instances) {
            foreach ($instances as $instance) {
                $statusColor = $instance->status === 'UP' ? "\033[32mUP\033[0m" : "\033[31mDOWN\033[0m";
                printf(
                    "%-20s %-22s %-20s %s\n",
                    $serviceName,
                    $instance->id,
                    $instance->getEndpoint(),
                    $statusColor
                );
            }
        }
        $this->line(str_repeat('-', 75));

        return 0;
    }
}
