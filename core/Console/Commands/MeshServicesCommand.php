<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\ServiceMesh\ServiceMeshManager;

class MeshServicesCommand extends Command
{
    protected string $name = 'mesh:services';
    protected string $signature = 'mesh:services {--service= : Filter by service name} {--healthy : Show only healthy instances}';
    protected string $description = 'List all registered microservice instances, endpoints, and health status in the mesh';

    public function handle(): int
    {
        /** @var ServiceMeshManager $manager */
        $manager = function_exists('mesh') ? mesh() : (function_exists('app') && app()->has('mesh') ? app('mesh') : new ServiceMeshManager());

        $serviceFilter = $this->option('service') ? (string) $this->option('service') : null;
        $onlyHealthy = (bool) $this->option('healthy');

        $allServices = $manager->getAllServices();

        $this->info("==========================================================================================");
        $this->info("                 Zero-Trust Service Mesh: Registered Microservice Instances");
        $this->info("==========================================================================================");

        if (empty($allServices)) {
            $this->line("No microservices currently registered in the service mesh.");
            return 0;
        }

        $headers = ['Service Name', 'Instance ID', 'Endpoint', 'Subset', 'Weight', 'Status', 'Active Conns', 'SPIFFE ID'];
        $rows = [];
        $totalCount = 0;

        foreach ($allServices as $serviceName => $instances) {
            if ($serviceFilter !== null && $serviceName !== $serviceFilter) {
                continue;
            }

            foreach ($instances as $inst) {
                if ($onlyHealthy && !$inst->isAvailable()) {
                    continue;
                }

                $statusColor = match ($inst->getStatus()) {
                    'HEALTHY' => '<info>HEALTHY</info>',
                    'DEGRADED' => '<comment>DEGRADED</comment>',
                    'EJECTED' => '<error>EJECTED</error>',
                    default => '<error>' . $inst->getStatus() . '</error>',
                };

                $rows[] = [
                    $inst->getServiceName(),
                    substr($inst->getId(), 0, 16),
                    $inst->getAddress(),
                    $inst->getSubset(),
                    (string) $inst->getWeight(),
                    $statusColor,
                    (string) $inst->getActiveConnections(),
                    $inst->getSpiffeId()?->getServiceAccount() ?? 'N/A',
                ];
                $totalCount++;
            }
        }

        $this->table($headers, $rows);
        $this->line("Total Registered Instances: {$totalCount}");

        return 0;
    }
}
