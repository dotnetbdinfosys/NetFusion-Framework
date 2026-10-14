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

interface ExporterInterface
{
    /**
     * Export collected metrics into target format string or dispatch to collector.
     *
     * @param MetricInterface[] $metrics
     * @return string|bool
     */
    public function export(array $metrics): mixed;

    /**
     * Get HTTP Content-Type header corresponding to exported format.
     */
    public function getContentType(): string;
}
