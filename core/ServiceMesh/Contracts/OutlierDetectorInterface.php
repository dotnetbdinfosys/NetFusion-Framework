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

namespace NetFusion\Core\ServiceMesh\Contracts;

use NetFusion\Core\ServiceMesh\Health\ServiceInstance;

interface OutlierDetectorInterface
{
    /**
     * Inspect and evaluate instance response. Ejects instance if consecutive error threshold exceeded.
     */
    public function recordResult(ServiceInstance $instance, int $statusCode, float $latencyMs = 0.0): void;

    /**
     * Check and restore instances whose ejection quarantine duration has elapsed.
     *
     * @param array<ServiceInstance> $instances
     */
    public function evaluateQuarantine(array $instances): void;
}
