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
 * CLI command: php netfusion gateway:health
 *
 * Runs active health checks on all configured upstream backends and reports
 * their current status plus circuit breaker state.
 */
class GatewayHealthCommand extends Command
{
    protected string $signature   = 'gateway:health';
    protected string $description = 'Check health of all upstream servers in the API gateway';

    public function handle(): int
    {
        /** @var GatewayManager|null $gateway */
        $gateway = null;
        try {
            $gateway = $this->app ? $this->app->make(GatewayManager::class) : null;
        } catch (\Throwable) {
            // Not bound
        }

        if ($gateway === null) {
            $this->line('<comment>Gateway not configured.</comment>');
            return 0;
        }

        $this->line('');
        $this->line('<info>Running upstream health checks …</info>');
        $this->line(str_repeat('─', 60));

        $results = $gateway->checkHealth();

        if (empty($results)) {
            $this->line('  No upstreams configured.');
        } else {
            $healthy   = 0;
            $unhealthy = 0;
            foreach ($results as $upstreamId => $isHealthy) {
                $status = $isHealthy ? '<fg=green>✓ HEALTHY  </>' : '<fg=red>✗ UNHEALTHY</>';
                $this->line(sprintf('  %s  %s', $status, $upstreamId));
                $isHealthy ? $healthy++ : $unhealthy++;
            }
        }

        $this->line(str_repeat('─', 60));
        $this->line(sprintf(
            '  Healthy: %d  |  Unhealthy: %d  |  Total: %d',
            $healthy ?? 0,
            $unhealthy ?? 0,
            count($results)
        ));
        $this->line('');

        return ($unhealthy ?? 0) > 0 ? 1 : 0;
    }
}
