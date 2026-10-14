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

namespace NetFusion\Core\Search\Models;

class FacetResult
{
    /**
     * @param string $field Facet field name (e.g. 'category', 'brand')
     * @param array<string, int> $counts Value => Frequency count
     * @param array<string, int> $ranges Range bucket => Count (e.g. '0-50' => 12)
     */
    public function __construct(
        public string $field,
        public array $counts = [],
        public array $ranges = []
    ) {
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getCounts(): array
    {
        return $this->counts;
    }

    public function getRanges(): array
    {
        return $this->ranges;
    }

    public function getCount(string $value): int
    {
        return $this->counts[$value] ?? 0;
    }
}
