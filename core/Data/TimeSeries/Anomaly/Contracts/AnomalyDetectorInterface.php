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

namespace NetFusion\Core\Data\TimeSeries\Anomaly\Contracts;

use NetFusion\Core\Data\TimeSeries\Anomaly\AnomalyResult;
use NetFusion\Core\Data\TimeSeries\TimeSeries;

interface AnomalyDetectorInterface
{
    /**
     * Run anomaly detection against a time series.
     *
     * @param  TimeSeries           $series
     * @param  array<string, mixed> $options
     * @return AnomalyResult
     */
    public function detect(TimeSeries $series, array $options = []): AnomalyResult;

    /**
     * Get detector name/method.
     */
    public function getName(): string;
}
