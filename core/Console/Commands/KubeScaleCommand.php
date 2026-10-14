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
use Throwable;

/**
 * Class KubeScaleCommand
 *
 * CLI: php netfusion kube:scale deployment <name> --replicas=N [-n namespace]
 */
class KubeScaleCommand extends Command
{
    protected string $name = 'kube:scale';
    protected string $description = 'Set a new size for a Kubernetes Deployment';

    public function handle(array $args = []): int
    {
        $deploymentName = null;
        $replicas = null;
        $namespace = 'default';

        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if (!is_string($arg)) {
                continue;
            }
            if (str_starts_with($arg, '--replicas=')) {
                $replicas = (int) substr($arg, 11);
            } elseif ($arg === '-n' && isset($args[$i + 1])) {
                $namespace = (string) $args[$i + 1];
                $i++;
            } elseif (str_starts_with($arg, '--namespace=')) {
                $namespace = substr($arg, 12);
            } elseif ($arg === 'deployment' || $arg === 'deploy') {
                continue;
            } elseif ($deploymentName === null && !str_starts_with($arg, '-')) {
                $deploymentName = $arg;
            }
        }

        if ($deploymentName === null || $replicas === null) {
            $this->error("Usage: php netfusion kube:scale deployment <name> --replicas=<count> [-n namespace]");
            return 1;
        }

        $kube = kube();

        try {
            $deployment = $kube->scaleDeployment($deploymentName, $replicas, $namespace);
            $this->success("deployment.apps/{$deployment->getName()} scaled to {$deployment->getReplicas()} replicas.");
            return 0;
        } catch (Throwable $e) {
            $this->error("Failed to scale deployment: {$e->getMessage()}");
            return 1;
        }
    }
}
