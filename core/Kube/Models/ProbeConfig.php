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

namespace NetFusion\Core\Kube\Models;

use NetFusion\Core\Kube\Enums\ProbeType;

/**
 * Configuration for container health probes (liveness, readiness, startup).
 */
class ProbeConfig
{
    /**
     * @param ProbeType $type
     * @param string $mechanism 'httpGet', 'tcpSocket', 'exec'
     * @param array<string, mixed> $spec Parameters (path, port, command, etc.)
     * @param int $initialDelaySeconds Seconds to wait after container start before probing
     * @param int $periodSeconds How often to perform the probe
     * @param int $timeoutSeconds Seconds after which probe times out
     * @param int $successThreshold Minimum consecutive successes to be considered successful
     * @param int $failureThreshold Minimum consecutive failures to be considered failed
     */
    public function __construct(
        protected ProbeType $type,
        protected string $mechanism = 'httpGet',
        protected array $spec = [],
        protected int $initialDelaySeconds = 0,
        protected int $periodSeconds = 10,
        protected int $timeoutSeconds = 1,
        protected int $successThreshold = 1,
        protected int $failureThreshold = 3
    ) {
    }

    public function getType(): ProbeType
    {
        return $this->type;
    }

    public function getMechanism(): string
    {
        return $this->mechanism;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSpec(): array
    {
        return $this->spec;
    }

    public function getInitialDelaySeconds(): int
    {
        return $this->initialDelaySeconds;
    }

    public function getPeriodSeconds(): int
    {
        return $this->periodSeconds;
    }

    public function getTimeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    public function getSuccessThreshold(): int
    {
        return $this->successThreshold;
    }

    public function getFailureThreshold(): int
    {
        return $this->failureThreshold;
    }
}
