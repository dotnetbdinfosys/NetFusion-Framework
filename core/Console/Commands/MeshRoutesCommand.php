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

class MeshRoutesCommand extends Command
{
    protected string $name = 'mesh:routes';
    protected string $signature = 'mesh:routes {--service= : Filter by destination service}';
    protected string $description = 'List all traffic routes, canary split weights, and chaos fault injection rules';

    public function handle(): int
    {
        /** @var ServiceMeshManager $manager */
        $manager = function_exists('mesh') ? mesh() : (function_exists('app') && app()->has('mesh') ? app('mesh') : new ServiceMeshManager());

        $serviceFilter = $this->option('service') ? (string) $this->option('service') : null;
        $routes = $manager->getRoutes();

        $this->info("==========================================================================================");
        $this->info("                 Service Mesh: Traffic Routes & Canary Splitting Configurations");
        $this->info("==========================================================================================");

        if (empty($routes)) {
            $this->line("No traffic routes currently configured in the mesh.");
            return 0;
        }

        $headers = ['Route Name', 'Destination', 'Path Prefix / Exact', 'Timeout', 'Canary Split', 'Fault Injection', 'Retries'];
        $rows = [];

        foreach ($routes as $route) {
            if ($serviceFilter !== null && $route->getServiceName() !== $serviceFilter) {
                continue;
            }

            $path = $route->toArray()['exact_path'] ?? ($route->toArray()['path_prefix'] ?? '*');
            $splitter = $route->getSplitter();
            $splitStr = $splitter !== null ? json_encode($splitter->getWeights()) : '100% v1';
            $fault = $route->getFaultInjector();
            $faultStr = $fault !== null ? 'DELAY/ABORT' : 'None';
            $retry = $route->getRetryPolicy();
            $retryStr = $retry !== null ? ($retry->getMaxRetries() . ' retries') : 'Default';

            $rows[] = [
                $route->getName(),
                $route->getServiceName(),
                $path,
                $route->getTimeoutMs() . 'ms',
                $splitStr,
                $faultStr,
                $retryStr,
            ];
        }

        $this->table($headers, $rows);
        $this->line("Total Active Routes: " . count($routes));

        return 0;
    }
}
