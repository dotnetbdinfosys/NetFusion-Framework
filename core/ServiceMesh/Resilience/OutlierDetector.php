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

namespace NetFusion\Core\ServiceMesh\Resilience;

use JsonSerializable;
use NetFusion\Core\ServiceMesh\Contracts\OutlierDetectorInterface;
use NetFusion\Core\ServiceMesh\Health\ServiceInstance;

class OutlierDetector implements OutlierDetectorInterface, JsonSerializable
{
    protected int $consecutive5xxThreshold;
    protected float $baseEjectionDurationSeconds;
    protected int $maxEjectionPercent;
    protected int $totalEjections = 0;

    public function __construct(
        int $consecutive5xxThreshold = 5,
        float $baseEjectionDurationSeconds = 30.0,
        int $maxEjectionPercent = 50
    ) {
        $this->consecutive5xxThreshold = max(1, $consecutive5xxThreshold);
        $this->baseEjectionDurationSeconds = max(0.1, $baseEjectionDurationSeconds);
        $this->maxEjectionPercent = max(1, min(100, $maxEjectionPercent));
    }

    public static function of(
        int $consecutiveErrors = 5,
        float $ejectionSeconds = 30.0
    ): self {
        return new self($consecutiveErrors, $ejectionSeconds);
    }

    public function recordResult(ServiceInstance $instance, int $statusCode, float $latencyMs = 0.0): void
    {
        if ($statusCode >= 500) {
            $errors = $instance->recordError();
            if ($errors >= $this->consecutive5xxThreshold && $instance->getStatus() !== ServiceInstance::STATUS_EJECTED) {
                $instance->eject($this->baseEjectionDurationSeconds);
                $this->totalEjections++;
            }
        } else {
            $instance->recordSuccess();
        }
    }

    public function evaluateQuarantine(array $instances): void
    {
        foreach ($instances as $instance) {
            // isAvailable() will automatically restore instance if ejection duration elapsed
            $instance->isAvailable();
        }
    }

    public function getTotalEjections(): int
    {
        return $this->totalEjections;
    }

    public function toArray(): array
    {
        return [
            'consecutive_5xx_threshold' => $this->consecutive5xxThreshold,
            'base_ejection_duration_seconds' => $this->baseEjectionDurationSeconds,
            'max_ejection_percent' => $this->maxEjectionPercent,
            'total_ejections' => $this->totalEjections,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
