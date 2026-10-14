<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Exporters;

/**
 * Class PrometheusExporter
 *
 * Formats metrics into Prometheus text/plain (v0.0.4) exposition format ready for /metrics scraping.
 */
class PrometheusExporter implements ExporterInterface
{
    public function exportTraces(array $spans): string
    {
        return ''; // Prometheus only consumes metrics
    }

    /**
     * Export metric snapshot into Prometheus text exposition format.
     */
    public function exportMetrics(array $metrics): string
    {
        $lines = [];

        foreach ($metrics as $metric) {
            $rawName = $metric['name'] ?? '';
            $sanitizedName = preg_replace('/[^a-zA-Z0-9_:]/', '_', $rawName);
            $desc = $metric['description'] ?? '';
            $type = $metric['type'] ?? 'gauge';
            $points = $metric['points'] ?? [];

            $promType = match ($type) {
                'counter' => 'counter',
                'histogram' => 'histogram',
                default => 'gauge',
            };

            if ($desc !== '') {
                $lines[] = "# HELP {$sanitizedName} {$desc}";
            }
            $lines[] = "# TYPE {$sanitizedName} {$promType}";

            foreach ($points as $point) {
                $attrs = $point['attributes'] ?? [];

                if ($type === 'histogram') {
                    $boundaries = $point['boundaries'] ?? [];
                    $bucketCounts = $point['bucket_counts'] ?? [];
                    $cumulativeCount = 0;

                    // Write each boundary bucket
                    foreach ($boundaries as $idx => $bound) {
                        $cumulativeCount += (int) ($bucketCounts[$idx] ?? 0);
                        $labelStr = $this->formatLabels(array_merge($attrs, ['le' => (string) $bound]));
                        $lines[] = "{$sanitizedName}_bucket{$labelStr} {$cumulativeCount}";
                    }

                    // +Inf bucket
                    $cumulativeCount += (int) ($bucketCounts[count($boundaries)] ?? 0);
                    $labelStrInf = $this->formatLabels(array_merge($attrs, ['le' => '+Inf']));
                    $lines[] = "{$sanitizedName}_bucket{$labelStrInf} {$cumulativeCount}";

                    // Sum & Count
                    $baseLabels = $this->formatLabels($attrs);
                    $lines[] = "{$sanitizedName}_sum{$baseLabels} " . ($point['sum'] ?? 0);
                    $lines[] = "{$sanitizedName}_count{$baseLabels} " . ($point['count'] ?? 0);
                } else {
                    $val = $point['value'] ?? 0;
                    $labelStr = $this->formatLabels($attrs);
                    $lines[] = "{$sanitizedName}{$labelStr} {$val}";
                }
            }

            $lines[] = ''; // Blank line separator
        }

        return implode("\n", $lines);
    }

    protected function formatLabels(array $attributes): string
    {
        if (empty($attributes)) {
            return '';
        }
        $pairs = [];
        foreach ($attributes as $k => $v) {
            $key = preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $k);
            $val = addcslashes((string) $v, "\"\n\\");
            $pairs[] = "{$key}=\"{$val}\"";
        }
        return '{' . implode(',', $pairs) . '}';
    }
}
