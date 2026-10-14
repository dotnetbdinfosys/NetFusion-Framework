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
 * Class KubeGetCommand
 *
 * CLI: php netfusion kube:get <pods|nodes|deployments|services|all> [-n namespace]
 */
class KubeGetCommand extends Command
{
    protected string $name = 'kube:get';
    protected string $description = 'Display one or many Kubernetes cluster resources (pods, nodes, deployments, services)';

    public function handle(array $args = []): int
    {
        $resourceType = 'pods';
        $namespace = null;

        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if (!is_string($arg)) {
                continue;
            }
            if ($arg === '-n' && isset($args[$i + 1])) {
                $namespace = (string) $args[$i + 1];
                $i++;
            } elseif (str_starts_with($arg, '--namespace=')) {
                $namespace = substr($arg, 12);
            } elseif (!str_starts_with($arg, '-')) {
                $resourceType = strtolower($arg);
            }
        }

        $kube = kube();

        switch ($resourceType) {
            case 'nodes':
            case 'node':
            case 'no':
                $nodes = $kube->getNodes();
                $this->info("NAME\t\tSTATUS\tINTERNAL-IP\tPODS\tCPU\tMEMORY");
                foreach ($nodes as $node) {
                    $alloc = $node->getAllocatable();
                    $podCount = count($node->getAssignedPods());
                    $this->line("{$node->getName()}\t{$node->getStatus()}\t{$node->getInternalIp()}\t{$podCount}\t{$alloc->getCpuMillis()}m\t{$alloc->getMemoryMib()}Mi");
                }
                break;

            case 'deployments':
            case 'deployment':
            case 'deploy':
                $deps = $kube->getDeployments($namespace);
                $this->info("NAME\t\tREADY\tUP-TO-DATE\tAVAILABLE");
                foreach ($deps as $d) {
                    $this->line("{$d->getName()}\t\t{$d->getReadyReplicas()}/{$d->getReplicas()}\t{$d->getReplicas()}\t\t{$d->getAvailableReplicas()}");
                }
                break;

            case 'services':
            case 'service':
            case 'svc':
                $svcs = $kube->getServices($namespace);
                $this->info("NAME\t\tTYPE\t\tCLUSTER-IP\tPORT(S)\tENDPOINTS");
                foreach ($svcs as $s) {
                    $portsStr = implode(',', array_map(fn($p) => "{$p['port']}/{$p['protocol']}", $s->getPorts()));
                    $endStr = implode(',', $s->getEndpoints()) ?: '<none>';
                    $this->line("{$s->getName()}\t{$s->getType()->value}\t{$s->getClusterIp()}\t{$portsStr}\t{$endStr}");
                }
                break;

            case 'pods':
            case 'pod':
            case 'po':
            default:
                $pods = $kube->getPods($namespace);
                $this->info("NAME\t\t\tREADY\tSTATUS\tRESTARTS\tIP\t\tNODE");
                foreach ($pods as $p) {
                    $readyCount = 0;
                    $restarts = 0;
                    foreach ($p->getContainerStatuses() as $cs) {
                        if ($cs->isReady()) {
                            $readyCount++;
                        }
                        $restarts += $cs->getRestartCount();
                    }
                    $totalC = count($p->getContainers());
                    $node = $p->getNodeName() ?? '<none>';
                    $ip = $p->getIp() ?? '<none>';
                    $this->line("{$p->getName()}\t{$readyCount}/{$totalC}\t{$p->getPhase()->value}\t{$restarts}\t\t{$ip}\t{$node}");
                }
                break;
        }

        return 0;
    }
}
