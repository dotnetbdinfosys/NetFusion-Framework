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

namespace NetFusion\Core\Data\TimeSeries\Anomaly;

use InvalidArgumentException;
use NetFusion\Core\Data\TimeSeries\Anomaly\Contracts\AnomalyDetectorInterface;
use NetFusion\Core\Data\TimeSeries\TimeSeries;

class AnomalyDetector
{
    /** @var array<string, AnomalyDetectorInterface> */
    protected array $detectors = [];

    public function __construct()
    {
        $this->register(new ZScoreDetector());
        $this->register(new IqrDetector());
        $this->register(new EwmaDetector());
    }

    public function register(AnomalyDetectorInterface $detector): self
    {
        $this->detectors[$detector->getName()] = $detector;
        return $this;
    }

    public function get(string $name): AnomalyDetectorInterface
    {
        if (!isset($this->detectors[$name])) {
            throw new InvalidArgumentException("Anomaly detector '{$name}' is not registered.");
        }
        return $this->detectors[$name];
    }

    public function zscore(TimeSeries $series, array $options = []): AnomalyResult
    {
        return $this->get('zscore')->detect($series, $options);
    }

    public function iqr(TimeSeries $series, array $options = []): AnomalyResult
    {
        return $this->get('iqr')->detect($series, $options);
    }

    public function ewma(TimeSeries $series, array $options = []): AnomalyResult
    {
        return $this->get('ewma')->detect($series, $options);
    }

    /**
     * Run ensemble anomaly detection (majority voting across detectors).
     */
    public function ensemble(TimeSeries $series, array $options = []): AnomalyResult
    {
        $votes = 0;
        $results = [];

        foreach ($this->detectors as $detector) {
            $res = $detector->detect($series, $options);
            $results[$detector->getName()] = $res;
            if ($res->isAnomaly) {
                $votes++;
            }
        }

        $majority = $votes >= ceil(count($this->detectors) / 2.0);
        $primary = $results['zscore'] ?? reset($results);

        $mergedAnomalies = [];
        foreach ($results as $res) {
            foreach ($res->anomalies as $a) {
                $mergedAnomalies[] = $a;
            }
        }

        return new AnomalyResult(
            isAnomaly: $majority,
            score: (float)$votes,
            expectedValue: $primary->expectedValue,
            actualValue: $primary->actualValue,
            threshold: (float)ceil(count($this->detectors) / 2.0),
            method: 'ensemble',
            anomalies: $mergedAnomalies
        );
    }
}
