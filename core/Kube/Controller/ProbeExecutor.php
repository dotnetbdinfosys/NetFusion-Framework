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

namespace NetFusion\Core\Kube\Controller;

use NetFusion\Core\Kube\Models\ContainerSpec;
use NetFusion\Core\Kube\Models\Pod;
use NetFusion\Core\Kube\Models\ProbeConfig;

/**
 * Executes container health check probes (liveness, readiness, startup).
 */
class ProbeExecutor
{
    /**
     * @var array<string, callable> Mock probe handlers keyed by "pod:container:type"
     */
    protected array $mockHandlers = [];

    /**
     * Register a custom probe handler callback for testing/custom simulation.
     *
     * @param string $key e.g. "nginx:liveness" or "*:readiness"
     */
    public function registerHandler(string $key, callable $handler): void
    {
        $this->mockHandlers[$key] = $handler;
    }

    /**
     * Execute a probe and determine if it succeeds.
     */
    public function execute(Pod $pod, ContainerSpec $container, ProbeConfig $probe): bool
    {
        $key = "{$container->getName()}:{$probe->getType()->value}";
        $wildcardKey = "*:{$probe->getType()->value}";

        if (isset($this->mockHandlers[$key])) {
            return (bool) ($this->mockHandlers[$key])($pod, $container, $probe);
        }

        if (isset($this->mockHandlers[$wildcardKey])) {
            return (bool) ($this->mockHandlers[$wildcardKey])($pod, $container, $probe);
        }

        return match ($probe->getMechanism()) {
            'httpGet'   => $this->executeHttpGet($probe->getSpec()),
            'tcpSocket' => $this->executeTcpSocket($probe->getSpec()),
            'exec'      => $this->executeExec($probe->getSpec()),
            default     => true,
        };
    }

    /**
     * Execute simulated HTTP GET probe.
     *
     * @param array<string, mixed> $spec
     */
    protected function executeHttpGet(array $spec): bool
    {
        // By default, if no external network is reached or mock is set, verify spec validity
        $path = $spec['path'] ?? '/';
        $port = (int) ($spec['port'] ?? 80);

        // Valid simulated probe returns true unless explicitly configured otherwise
        return $port > 0 && str_starts_with((string) $path, '/');
    }

    /**
     * Execute simulated TCP socket probe.
     *
     * @param array<string, mixed> $spec
     */
    protected function executeTcpSocket(array $spec): bool
    {
        $port = (int) ($spec['port'] ?? 0);
        return $port > 0 && $port <= 65535;
    }

    /**
     * Execute command probe.
     *
     * @param array<string, mixed> $spec
     */
    protected function executeExec(array $spec): bool
    {
        $command = $spec['command'] ?? [];
        if (is_array($command) && !empty($command)) {
            // If command is 'exit 0' or 'true' or healthy check
            $cmdStr = implode(' ', $command);
            if (str_contains($cmdStr, 'exit 1') || str_contains($cmdStr, 'false')) {
                return false;
            }
            return true;
        }
        return true;
    }
}
