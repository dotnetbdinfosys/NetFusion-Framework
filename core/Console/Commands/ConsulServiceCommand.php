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

/**
 * CLI command to register, deregister, discover, and list Consul catalog services.
 * Usage: php netfusion consul:service <list|register|deregister|discover> [name] [--address=] [--port=] [--tags=]
 */
class ConsulServiceCommand extends Command
{
    protected string $name = 'consul:service';
    protected string $description = 'Manage and discover Consul service catalog instances';

    public function handle(array $args = []): int
    {
        $action = 'list';
        $serviceName = null;
        $address = '127.0.0.1';
        $port = 80;
        $id = '';
        $tags = [];

        $positional = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = (string)$args[$i];
            if (str_starts_with($arg, '--address=')) {
                $address = substr($arg, 10);
            } elseif (str_starts_with($arg, '--port=')) {
                $port = (int)substr($arg, 7);
            } elseif (str_starts_with($arg, '--id=')) {
                $id = substr($arg, 5);
            } elseif (str_starts_with($arg, '--tags=')) {
                $tags = explode(',', substr($arg, 7));
            } elseif (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        if (!empty($positional)) {
            $action = strtolower($positional[0]);
            $serviceName = $positional[1] ?? null;
        }

        $consul = function_exists('consul') ? consul() : $this->app->make(\NetFusion\Core\Consul\ConsulManager::class);

        switch ($action) {
            case 'register':
                if ($serviceName === null) {
                    $this->error('Usage: php netfusion consul:service register <name> [--address=127.0.0.1] [--port=80] [--tags=v1,web]');
                    return 1;
                }
                $inst = $consul->registerService($serviceName, $address, $port, $id, $tags);
                $this->info("Registered service '{$inst->getName()}' [ID: {$inst->getId()}] at {$inst->getAddress()}:{$inst->getPort()}");
                return 0;

            case 'deregister':
                $targetId = $serviceName; // In deregister, positional[1] is instance id
                if ($targetId === null) {
                    $this->error('Usage: php netfusion consul:service deregister <instance-id>');
                    return 1;
                }
                $ok = $consul->deregisterService($targetId);
                if ($ok) {
                    $this->info("Deregistered service instance '{$targetId}'.");
                    return 0;
                }
                $this->error("Service instance '{$targetId}' not found.");
                return 1;

            case 'discover':
                if ($serviceName === null) {
                    $this->error('Usage: php netfusion consul:service discover <name> [--tags=tag1,tag2]');
                    return 1;
                }
                $instances = $consul->discover($serviceName, $tags);
                if (empty($instances)) {
                    $this->line("No healthy instances found for service '{$serviceName}'.");
                    return 0;
                }
                $this->info("Found " . count($instances) . " instance(s) for '{$serviceName}':");
                $this->line(sprintf('%-24s %-20s %-8s %-10s %-20s', 'INSTANCE ID', 'ADDRESS', 'PORT', 'STATUS', 'TAGS'));
                $this->line(str_repeat('-', 85));
                foreach ($instances as $inst) {
                    $this->line(sprintf(
                        '%-24s %-20s %-8d %-10s %-20s',
                        $inst->getId(),
                        $inst->getAddress(),
                        $inst->getPort(),
                        $inst->getStatus()->value,
                        implode(',', $inst->getTags())
                    ));
                }
                return 0;

            case 'list':
            default:
                $services = $consul->getCatalog()->listServices();
                if (empty($services)) {
                    $this->line('No services registered in Consul catalog.');
                    return 0;
                }
                $this->info('Registered Services in Catalog:');
                $this->line(sprintf('%-30s %-15s %-30s', 'SERVICE NAME', 'INSTANCES', 'TAGS'));
                $this->line(str_repeat('-', 75));
                foreach ($services as $name => $tagList) {
                    $count = count($consul->getCatalog()->getServiceInstances($name));
                    $this->line(sprintf('%-30s %-15d %-30s', $name, $count, implode(', ', $tagList)));
                }
                return 0;
        }
    }
}
