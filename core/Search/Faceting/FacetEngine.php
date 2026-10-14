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

namespace NetFusion\Core\Search\Faceting;

use NetFusion\Core\Search\Models\Document;
use NetFusion\Core\Search\Models\FacetResult;

class FacetEngine
{
    /**
     * @var array<string, array<string, array{min?: float, max?: float}>> Configured range buckets per field
     */
    protected array $rangeConfigs = [];

    /**
     * @param array<string, array<string, array{min?: float, max?: float}>> $rangeConfigs
     */
    public function __construct(array $rangeConfigs = [])
    {
        $this->rangeConfigs = $rangeConfigs;
    }

    public function configureRanges(string $field, array $buckets): static
    {
        $this->rangeConfigs[$field] = $buckets;
        return $this;
    }

    /**
     * Compute faceted aggregations across matching search documents.
     *
     * @param array<Document> $documents
     * @param array<string> $facetFields
     * @return array<string, FacetResult>
     */
    public function computeFacets(array $documents, array $facetFields): array
    {
        $results = [];

        foreach ($facetFields as $field) {
            $counts = [];
            $ranges = [];
            $hasRangeConfig = isset($this->rangeConfigs[$field]);
            $configuredBuckets = $this->rangeConfigs[$field] ?? [];

            // Initialize range counts if defined
            foreach ($configuredBuckets as $bucketName => $range) {
                $ranges[$bucketName] = 0;
            }

            foreach ($documents as $doc) {
                $val = $doc->get($field);
                if ($val === null) {
                    continue;
                }

                // Handle Array / Multi-value tags
                if (is_array($val)) {
                    foreach ($val as $item) {
                        $strItem = (string)$item;
                        $counts[$strItem] = ($counts[$strItem] ?? 0) + 1;
                    }
                    continue;
                }

                // Handle Numeric Range Bucketing
                if ($hasRangeConfig && is_numeric($val)) {
                    $num = (float)$val;
                    foreach ($configuredBuckets as $bucketName => $bounds) {
                        $min = $bounds['min'] ?? -INF;
                        $max = $bounds['max'] ?? INF;
                        if ($num >= $min && $num <= $max) {
                            $ranges[$bucketName]++;
                        }
                    }
                }

                // Term counts
                $strVal = (string)$val;
                $counts[$strVal] = ($counts[$strVal] ?? 0) + 1;
            }

            // Sort counts by frequency descending
            arsort($counts);

            $results[$field] = new FacetResult($field, $counts, $ranges);
        }

        return $results;
    }
}
