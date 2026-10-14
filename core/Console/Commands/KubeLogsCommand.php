<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Kube\KubeManager;

/**
 * Class KubeLogsCommand
 *
 * CLI: php netfusion kube:logs <pod-name> [-c container] [-n namespace]
 */
class KubeLogsCommand extends Command
{
    protected string $name = 'kube:logs';
    protected string $description = 'Print the logs for a container in a Pod';

    public function handle(array $args = []): int
    {
        $podName = null;
        $containerName = null;
        $namespace = 'default';

        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if (!is_string($arg)) {
                continue;
            }
            if ($arg === '-c' && isset($args[$i + 1])) {
                $containerName = (string) $args[$i + 1];
                $i++;
            } elseif (str_starts_with($arg, '--container=')) {
                $containerName = substr($arg, 12);
            } elseif ($arg === '-n' && isset($args[$i + 1])) {
                $namespace = (string) $args[$i + 1];
                $i++;
            } elseif (str_starts_with($arg, '--namespace=')) {
                $namespace = substr($arg, 12);
            } elseif ($podName === null && !str_starts_with($arg, '-')) {
                $podName = $arg;
            }
        }

        if ($podName === null) {
            $this->error("Usage: php netfusion kube:logs <pod-name> [-c container] [-n namespace]");
            return 1;
        }

        $kube = kube();
        $pod = $kube->getPod($podName, $namespace);

        if ($pod === null) {
            $this->error("Pod '{$podName}' not found in namespace '{$namespace}'.");
            return 1;
        }

        $this->info("Logs for pod/{$pod->getName()} (phase: {$pod->getPhase()->value}):");
        foreach ($pod->getContainers() as $container) {
            if ($containerName !== null && $container->getName() !== $containerName) {
                continue;
            }
            $status = $pod->getContainerStatus($container->getName());
            $this->line("[{$container->getName()}] Image: {$container->getImage()}, State: {$status?->getState()->value}, Restarts: {$status?->getRestartCount()}");
            $this->line("[{$container->getName()}] Simulated container logs streaming: Initialized application on port(s): " . implode(', ', $container->getPorts()));
        }

        return 0;
    }
}
