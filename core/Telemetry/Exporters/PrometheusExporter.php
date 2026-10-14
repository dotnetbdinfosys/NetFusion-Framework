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

namespace NetFusion\Core\Telemetry\Exporters;

use NetFusion\Core\Telemetry\Contracts\ExporterInterface;
use NetFusion\Core\Telemetry\Contracts\MetricInterface;

class PrometheusExporter implements ExporterInterface
{
    public function getContentType(): string
    {
        return 'text/plain; version=0.0.4; charset=utf-8';
    }

    /**
     * @param MetricInterface[] $metrics
     * @return string
     */
    public function export(array $metrics): string
    {
        $lines = [];

        foreach ($metrics as $metric) {
            $name = $metric->getName();
            $type = $metric->getType();
            $desc = $metric->getDescription();

            // # HELP
            if ($desc !== '') {
                $lines[] = "# HELP {$name} " . $this->escapeHelp($desc);
            }

            // # TYPE
            $lines[] = "# TYPE {$name} {$type}";

            // Samples
            $samples = $metric->collect();
            foreach ($samples as $sample) {
                $metricName = $sample->getMetricName();
                $labelsStr = $this->formatLabels($sample->labels);
                $valueStr = $this->formatValue($sample->value);

                $lines[] = "{$metricName}{$labelsStr} {$valueStr}";
            }

            $lines[] = ''; // Empty line between metric families
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, string|int|float|bool> $labels
     * @return string
     */
    protected function formatLabels(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }

        ksort($labels);

        $parts = [];
        foreach ($labels as $k => $v) {
            $val = is_bool($v) ? ($v ? 'true' : 'false') : (string)$v;
            $parts[] = "{$k}=\"" . $this->escapeLabelValue($val) . "\"";
        }

        return '{' . implode(',', $parts) . '}';
    }

    protected function formatValue(float|int $value): string
    {
        if (is_infinite((float)$value)) {
            return $value > 0 ? '+Inf' : '-Inf';
        }

        if (is_nan((float)$value)) {
            return 'NaN';
        }

        if (is_float($value)) {
            // Avoid scientific notation if possible or format cleanly
            return rtrim(rtrim(sprintf('%.6f', $value), '0'), '.') ?: '0';
        }

        return (string)$value;
    }

    protected function escapeHelp(string $help): string
    {
        return str_replace(['\\', "\n"], ['\\\\', '\n'], $help);
    }

    protected function escapeLabelValue(string $val): string
    {
        return str_replace(['\\', "\"", "\n"], ['\\\\', '\\"', '\n'], $val);
    }
}
