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

namespace NetFusion\Core\Telemetry\Metrics;

use NetFusion\Core\Telemetry\Contracts\MetricInterface;

abstract class AbstractMetric implements MetricInterface
{
    /**
     * @param string $name
     * @param string $description
     * @param string $unit
     */
    public function __construct(
        protected string $name,
        protected string $description = '',
        protected string $unit = '1'
    ) {
        $this->name = $this->sanitizeMetricName($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    abstract public function getType(): string;

    /**
     * Normalize and sort labels into a canonical key.
     *
     * @param array<string, string|int|float|bool> $labels
     * @return string
     */
    protected function getLabelKey(array $labels): string
    {
        if (empty($labels)) {
            return '__default__';
        }

        ksort($labels);
        $parts = [];
        foreach ($labels as $k => $v) {
            $sanitizedKey = $this->sanitizeLabelKey((string)$k);
            $sanitizedVal = is_bool($v) ? ($v ? 'true' : 'false') : (string)$v;
            $parts[] = "{$sanitizedKey}=\"{$sanitizedVal}\"";
        }

        return implode(',', $parts);
    }

    /**
     * Normalize label array.
     *
     * @param array<string, string|int|float|bool> $labels
     * @return array<string, string>
     */
    protected function normalizeLabels(array $labels): array
    {
        $normalized = [];
        foreach ($labels as $k => $v) {
            $sanitizedKey = $this->sanitizeLabelKey((string)$k);
            $normalized[$sanitizedKey] = is_bool($v) ? ($v ? 'true' : 'false') : (string)$v;
        }
        return $normalized;
    }

    protected function sanitizeMetricName(string $name): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_:]/', '_', $name) ?? $name;
        return ltrim($clean, '0..9_');
    }

    protected function sanitizeLabelKey(string $key): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '_', $key) ?? $key;
        return ltrim($clean, '0..9_');
    }
}
