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

class MeshStatsCommand extends Command
{
    protected string $name = 'mesh:stats';
    protected string $signature = 'mesh:stats';
    protected string $description = 'Display topology, health, outlier ejections, and traffic metrics across the service mesh';

    public function handle(): int
    {
        /** @var ServiceMeshManager $manager */
        $manager = function_exists('mesh') ? mesh() : (function_exists('app') && app()->has('mesh') ? app('mesh') : new ServiceMeshManager());

        $stats = $manager->getStats();

        $this->info("==========================================================================================");
        $this->info("               Zero-Trust Service Mesh & Sidecar Proxy Runtime Topology");
        $this->info("==========================================================================================");
        $this->line("Trust Domain         : {$stats['trust_domain']}");
        $this->line("Mesh Engine Uptime   : {$stats['uptime_seconds']} seconds");
        $this->line("Total Services       : {$stats['total_services']}");
        $this->line("Total Instances      : {$stats['total_instances']}");
        $this->line("Healthy Instances    : {$stats['healthy_instances']}");
        $this->line("Registered Routes    : {$stats['registered_routes']}");
        $this->line("Zero-Trust Policies  : {$stats['registered_policies']}");
        $this->line("Active Sidecar Proxies: {$stats['active_sidecars']}");
        $this->line("------------------------------------------------------------------------------------------");

        return 0;
    }
}
