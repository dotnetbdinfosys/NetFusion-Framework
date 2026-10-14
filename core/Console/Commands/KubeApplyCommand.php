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
use NetFusion\Core\Kube\Models\Deployment;
use NetFusion\Core\Kube\Models\Node;
use NetFusion\Core\Kube\Models\Pod;
use NetFusion\Core\Kube\Models\Service;
use Throwable;

/**
 * Class KubeApplyCommand
 *
 * CLI: php netfusion kube:apply -f <manifest.yaml>
 */
class KubeApplyCommand extends Command
{
    protected string $name = 'kube:apply';
    protected string $description = 'Apply a declarative Kubernetes manifest (YAML/JSON) to the cluster';

    public function handle(array $args = []): int
    {
        $filePath = null;

        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if (!is_string($arg)) {
                continue;
            }
            if ($arg === '-f' && isset($args[$i + 1])) {
                $filePath = (string) $args[$i + 1];
                $i++;
            } elseif (str_starts_with($arg, '--filename=')) {
                $filePath = substr($arg, 11);
            } elseif (!str_starts_with($arg, '-') && $filePath === null) {
                $filePath = $arg;
            }
        }

        if ($filePath === null) {
            $this->error("Usage: php netfusion kube:apply -f <manifest.yaml>");
            return 1;
        }

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $kube = kube();
        $this->info("Applying manifest: {$filePath} ...");

        try {
            $content = (string) file_get_contents($filePath);
            $resources = $kube->apply($content);

            if (empty($resources)) {
                $this->warn("No valid Kubernetes resources found in manifest.");
                return 0;
            }

            foreach ($resources as $res) {
                if ($res instanceof Pod) {
                    $this->success("pod/{$res->getName()} created/configured (IP: {$res->getIp()}, Node: {$res->getNodeName()})");
                } elseif ($res instanceof Deployment) {
                    $this->success("deployment.apps/{$res->getName()} configured (Replicas: {$res->getReplicas()})");
                } elseif ($res instanceof Service) {
                    $this->success("service/{$res->getName()} configured (ClusterIP: {$res->getClusterIp()})");
                } elseif ($res instanceof Node) {
                    $this->success("node/{$res->getName()} registered");
                }
            }

            return 0;
        } catch (Throwable $e) {
            $this->error("Failed to apply manifest: {$e->getMessage()}");
            return 1;
        }
    }
}
