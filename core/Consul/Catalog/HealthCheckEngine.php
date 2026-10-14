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

namespace NetFusion\Core\Consul\Catalog;

use NetFusion\Core\Consul\Enums\CheckStatus;
use NetFusion\Core\Consul\Enums\CheckType;
use NetFusion\Core\Consul\Models\ServiceCheck;
use NetFusion\Core\Consul\Models\ServiceInstance;

/**
 * Multi-Protocol Health Check Engine.
 * Evaluates HTTP, TCP, TTL, and Script health checks.
 * Tracks failure durations and automatically evicts dead services after deregisterCriticalServiceAfter thresholds.
 */
class HealthCheckEngine
{
    protected ServiceCatalog $catalog;
    /** @var array<string, float> [checkId => timestampOfFirstCritical] */
    protected array $criticalTimestamps = [];

    public function __construct(ServiceCatalog $catalog)
    {
        $this->catalog = $catalog;
    }

    public function getCatalog(): ServiceCatalog
    {
        return $this->catalog;
    }

    /**
     * Evaluate a single health check.
     */
    public function evaluateCheck(ServiceCheck $check): CheckStatus
    {
        $status = CheckStatus::PASSING;
        $output = '';

        try {
            switch ($check->getType()) {
                case CheckType::HTTP:
                    $status = $this->evaluateHttpCheck($check, $output);
                    break;

                case CheckType::TCP:
                    $status = $this->evaluateTcpCheck($check, $output);
                    break;

                case CheckType::TTL:
                    $status = $this->evaluateTtlCheck($check, $output);
                    break;

                case CheckType::SCRIPT:
                    $status = $this->evaluateScriptCheck($check, $output);
                    break;
            }
        } catch (\Throwable $e) {
            $status = CheckStatus::CRITICAL;
            $output = 'Check exception: ' . $e->getMessage();
        }

        $check->setStatus($status, $output);

        // Track critical timestamps for eviction
        $checkId = $check->getId();
        if ($status === CheckStatus::CRITICAL) {
            if (!isset($this->criticalTimestamps[$checkId])) {
                $this->criticalTimestamps[$checkId] = microtime(true);
            }
        } else {
            unset($this->criticalTimestamps[$checkId]);
        }

        return $status;
    }

    /**
     * Evaluate an HTTP health check.
     */
    protected function evaluateHttpCheck(ServiceCheck $check, string &$output): CheckStatus
    {
        $url = $check->getHttpUrl();
        if (empty($url)) {
            $output = 'No HTTP URL specified';
            return CheckStatus::CRITICAL;
        }

        // Check if custom curl / stream context can be tested or mock-handled
        $context = stream_context_create([
            'http' => [
                'timeout' => $check->getTimeout(),
                'ignore_errors' => true,
                'method' => 'GET',
            ],
        ]);

        $fp = @fopen($url, 'r', false, $context);
        if (!$fp) {
            $output = "Connection refused to HTTP URL: {$url}";
            return CheckStatus::CRITICAL;
        }

        $meta = stream_get_meta_data($fp);
        fclose($fp);

        $headers = $meta['wrapper_data'] ?? [];
        $statusCode = 0;
        if (!empty($headers)) {
            if (preg_match('#HTTP/\S+\s+(\d{3})#i', $headers[0], $matches)) {
                $statusCode = (int)$matches[1];
            }
        }

        $output = "HTTP response status: {$statusCode}";
        if ($statusCode >= 200 && $statusCode <= 299) {
            return CheckStatus::PASSING;
        }
        if ($statusCode === 429) {
            return CheckStatus::WARNING;
        }

        return CheckStatus::CRITICAL;
    }

    /**
     * Evaluate a TCP socket health check.
     */
    protected function evaluateTcpCheck(ServiceCheck $check, string &$output): CheckStatus
    {
        $address = $check->getTcpAddress();
        if (empty($address)) {
            $output = 'No TCP address specified';
            return CheckStatus::CRITICAL;
        }

        $parts = explode(':', $address);
        $host = $parts[0];
        $port = isset($parts[1]) ? (int)$parts[1] : 80;

        $fp = @fsockopen($host, $port, $errno, $errstr, (float)$check->getTimeout());
        if (!$fp) {
            $output = "TCP connection failed to {$host}:{$port}: [{$errno}] {$errstr}";
            return CheckStatus::CRITICAL;
        }

        fclose($fp);
        $output = "TCP connection successful to {$host}:{$port}";
        return CheckStatus::PASSING;
    }

    /**
     * Evaluate a TTL check based on last touched time.
     */
    protected function evaluateTtlCheck(ServiceCheck $check, string &$output): CheckStatus
    {
        $now = microtime(true);
        $elapsed = $now - $check->getLastCheckedAt();
        $ttl = (float)$check->getInterval();

        if ($elapsed <= $ttl) {
            $output = sprintf('TTL OK: elapsed %.2fs <= %.2fs', $elapsed, $ttl);
            return CheckStatus::PASSING;
        }

        if ($elapsed <= ($ttl * 2)) {
            $output = sprintf('TTL WARNING: elapsed %.2fs > %.2fs', $elapsed, $ttl);
            return CheckStatus::WARNING;
        }

        $output = sprintf('TTL EXPIRED: elapsed %.2fs > %.2fs', $elapsed, $ttl * 2);
        return CheckStatus::CRITICAL;
    }

    /**
     * Evaluate a script callback check.
     */
    protected function evaluateScriptCheck(ServiceCheck $check, string &$output): CheckStatus
    {
        $callback = $check->getScriptCallback();
        if (!is_callable($callback)) {
            $output = 'No script callback registered';
            return CheckStatus::CRITICAL;
        }

        $result = $callback();
        if ($result === true || $result === CheckStatus::PASSING || $result === 0) {
            $output = 'Script passed';
            return CheckStatus::PASSING;
        }

        if ($result === CheckStatus::WARNING || $result === 1) {
            $output = 'Script warning';
            return CheckStatus::WARNING;
        }

        $output = 'Script critical';
        return CheckStatus::CRITICAL;
    }

    /**
     * Manually send TTL heartbeat/pass to a check.
     */
    public function passTtl(string $instanceId, string $checkId, string $note = ''): bool
    {
        $instance = $this->catalog->getInstance($instanceId);
        if ($instance === null) {
            return false;
        }

        $check = $instance->getCheck($checkId);
        if ($check === null || $check->getType() !== CheckType::TTL) {
            return false;
        }

        $check->setStatus(CheckStatus::PASSING, $note ?: 'TTL pass heartbeat received');
        $instance->recomputeStatus();
        unset($this->criticalTimestamps[$checkId]);
        return true;
    }

    /**
     * Run all health checks for all instances in the catalog.
     * Recomputes instance statuses and automatically evicts dead instances.
     *
     * @return array{checked: int, evicted: array<string>}
     */
    public function runAllChecks(float $currentTime = 0.0): array
    {
        $now = $currentTime > 0.0 ? $currentTime : microtime(true);
        $checkedCount = 0;
        $evictedInstances = [];

        foreach ($this->catalog->listServices() as $serviceName => $tags) {
            $instances = $this->catalog->getServiceInstances($serviceName);

            foreach ($instances as $inst) {
                $shouldDeregister = false;

                foreach ($inst->getChecks() as $check) {
                    $this->evaluateCheck($check);
                    $checkedCount++;

                    $checkId = $check->getId();
                    if ($check->getStatus() === CheckStatus::CRITICAL && isset($this->criticalTimestamps[$checkId])) {
                        $critDuration = $now - $this->criticalTimestamps[$checkId];
                        if ($critDuration >= $check->getDeregisterCriticalServiceAfter()) {
                            $shouldDeregister = true;
                        }
                    }
                }

                $inst->recomputeStatus();

                if ($shouldDeregister) {
                    $this->catalog->deregisterService($inst->getId());
                    $evictedInstances[] = $inst->getId();
                }
            }
        }

        return [
            'checked' => $checkedCount,
            'evicted' => $evictedInstances,
        ];
    }
}
