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

namespace NetFusion\Core\Gateway\Health;

use NetFusion\Core\Gateway\Contracts\HealthCheckerInterface;
use NetFusion\Core\Gateway\Contracts\UpstreamInterface;
use NetFusion\Core\Gateway\Models\Upstream;
use NetFusion\Core\Gateway\Models\UpstreamPool;

/**
 * Active health checker that probes upstreams via HTTP.
 * Uses PHP streams (fsockopen/stream_socket_client) — no cURL dependency.
 */
class HealthChecker implements HealthCheckerInterface
{
    public function __construct(
        private readonly string $healthPath = '/health',
        private readonly int $timeoutSeconds = 3,
        private readonly int $expectedStatus = 200,
        private readonly bool $simulate = false   // set true in tests to bypass real HTTP
    ) {}

    public function checkPool(UpstreamPool $pool): array
    {
        $results = [];
        foreach ($pool->all() as $upstream) {
            $healthy = $this->checkUpstream($upstream);
            $upstream->isHealthy() !== $healthy
                ? ($healthy ? $upstream->markHealthy() : $upstream->markUnhealthy())
                : null;
            $results[$upstream->getId()] = $healthy;
        }
        return $results;
    }

    public function checkUpstream(UpstreamInterface $upstream): bool
    {
        // In simulation mode always return healthy (for unit tests)
        if ($this->simulate) {
            return true;
        }

        $host    = $upstream->getHost();
        $port    = $upstream->getPort();
        $timeout = $this->timeoutSeconds;
        $path    = $this->healthPath;

        $errno  = 0;
        $errstr = '';

        $scheme = $upstream->getScheme() === 'https' ? 'ssl://' : '';
        $socket = @fsockopen($scheme . $host, $port, $errno, $errstr, $timeout);

        if (!$socket) {
            return false;
        }

        $request = implode("\r\n", [
            "GET {$path} HTTP/1.0",
            "Host: {$host}",
            "Connection: close",
            "",
            "",
        ]);

        fwrite($socket, $request);

        $response = '';
        $deadline  = microtime(true) + $timeout;
        while (!feof($socket) && microtime(true) < $deadline) {
            $response .= fread($socket, 512);
            if (str_contains($response, "\r\n\r\n")) {
                break;
            }
        }
        fclose($socket);

        // Parse HTTP status line
        if (preg_match('/^HTTP\/\d\.\d (\d{3})/', $response, $m)) {
            return (int)$m[1] === $this->expectedStatus;
        }

        return false;
    }

    public function isSimulated(): bool
    {
        return $this->simulate;
    }
}
