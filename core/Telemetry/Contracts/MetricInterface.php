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

namespace NetFusion\Core\Telemetry\Contracts;

use NetFusion\Core\Telemetry\Metrics\MetricSample;

interface MetricInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function getUnit(): string;
    public function getType(): string;
    /**
     * @return MetricSample[]
     */
    public function collect(): array;
    public function reset(): void;
}
