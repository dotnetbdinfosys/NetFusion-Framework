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
 * CLI command to evaluate health probes or submit TTL heartbeats.
 * Usage: php netfusion consul:check <run|pass> [instance_id] [--check_id=] [--note=]
 */
class ConsulCheckCommand extends Command
{
    protected string $name = 'consul:check';
    protected string $description = 'Evaluate health checks or pass TTL heartbeats for services';

    public function handle(array $args = []): int
    {
        $action = 'run';
        $instanceId = null;
        $checkId = null;
        $note = '';

        $positional = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = (string)$args[$i];
            if (str_starts_with($arg, '--check_id=')) {
                $checkId = substr($arg, 11);
            } elseif (str_starts_with($arg, '--note=')) {
                $note = substr($arg, 7);
            } elseif (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        if (!empty($positional)) {
            $action = strtolower($positional[0]);
            $instanceId = $positional[1] ?? null;
        }

        $consul = function_exists('consul') ? consul() : $this->app->make(\NetFusion\Core\Consul\ConsulManager::class);

        switch ($action) {
            case 'pass':
                if ($instanceId === null || $checkId === null) {
                    $this->error('Usage: php netfusion consul:check pass <instance_id> --check_id=<check_id> [--note="OK"]');
                    return 1;
                }
                $ok = $consul->passHealthCheck($instanceId, $checkId, $note);
                if ($ok) {
                    $this->info("Heartbeat passed for check '{$checkId}' on instance '{$instanceId}'.");
                    return 0;
                }
                $this->error("Failed to pass check '{$checkId}' on instance '{$instanceId}'. Check not found or not TTL.");
                return 1;

            case 'run':
            default:
                $result = $consul->checkHealth();
                $this->info("Evaluated {$result['checked']} health check(s).");
                if (!empty($result['evicted'])) {
                    $this->error("Evicted dead instances: " . implode(', ', $result['evicted']));
                } else {
                    $this->line("No dead instances evicted.");
                }
                return 0;
        }
    }
}
