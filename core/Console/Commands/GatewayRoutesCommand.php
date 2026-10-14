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
use NetFusion\Core\Gateway\GatewayManager;

/**
 * CLI command: php netfusion gateway:routes
 *
 * Lists all registered gateway routes with their upstreams and configuration.
 */
class GatewayRoutesCommand extends Command
{
    protected string $signature   = 'gateway:routes';
    protected string $description = 'List all registered API gateway routes';

    public function handle(): int
    {
        /** @var GatewayManager|null $gateway */
        $gateway = null;
        try {
            $gateway = $this->app ? $this->app->make(GatewayManager::class) : null;
        } catch (\Throwable) {
            // Not bound — show empty list
        }

        if ($gateway === null) {
            $this->line('<comment>Gateway not configured. No routes found.</comment>');
            return 0;
        }

        $routes = $gateway->getRoutesSummary();

        if (empty($routes)) {
            $this->line('<comment>No routes registered.</comment>');
            return 0;
        }

        $this->line('');
        $this->line('<info>API Gateway Routes</info>');
        $this->line(str_repeat('─', 80));

        foreach ($routes as $route) {
            $methods  = empty($route['methods']) ? 'ANY' : implode('|', $route['methods']);
            $hostname = $route['hostname'] === '*' ? '(any host)' : $route['hostname'];
            $priority = $route['priority'];
            $upstream = count($route['upstreams']);

            $this->line(sprintf(
                '  <fg=green>%-20s</> %-30s %-15s host=%-20s priority=%-4d upstreams=%d',
                $route['id'],
                $route['path_prefix'],
                $methods,
                $hostname,
                $priority,
                $upstream
            ));

            if (!empty($route['upstreams'])) {
                foreach ($route['upstreams'] as $u) {
                    $health = $u['healthy'] ? '<fg=green>✓ healthy</>' : '<fg=red>✗ unhealthy</>';
                    $this->line(sprintf(
                        '    └─ %s (weight=%d, conns=%d, avg_latency=%.2fms) %s',
                        $u['id'],
                        $u['weight'],
                        $u['connections'],
                        $u['avg_latency'],
                        $health
                    ));
                }
            }
        }

        $this->line(str_repeat('─', 80));
        $this->line(sprintf('  Total routes: %d', count($routes)));
        $this->line('');

        return 0;
    }
}
