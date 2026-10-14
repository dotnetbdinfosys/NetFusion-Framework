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

namespace NetFusion\Core\DataWarehouse\Cube;

use NetFusion\Core\DataWarehouse\Contracts\CubeInterface;
use NetFusion\Core\DataWarehouse\Schema\StarSchema;

class OLAPCube implements CubeInterface
{
    /**
     * @var array<int, array<string, mixed>> In-memory materialized fact & dimension rows
     */
    protected array $dataset = [];

    /**
     * @var array<string, mixed> Active slice/dice predicates
     */
    protected array $activeFilters = [];

    /**
     * @var array<string, string> Active hierarchy levels (Dimension => Level)
     */
    protected array $activeLevels = [];

    protected HierarchyNavigator $navigator;

    /**
     * @param string $name Cube identifier (e.g. 'sales_cube')
     * @param StarSchema $starSchema Underlying dimensional star schema
     * @param string $factTableName Base fact table name
     * @param array<string, string> $measures Default measure definitions (Name => Default Aggregation)
     * @param array<string, array<string>> $hierarchies Dimension hierarchies
     */
    public function __construct(
        protected string $name,
        protected StarSchema $starSchema,
        protected string $factTableName,
        protected array $measures = [],
        protected array $hierarchies = []
    ) {
        $this->navigator = new HierarchyNavigator($hierarchies);
        $this->materialize();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMeasures(): array
    {
        return $this->measures;
    }

    public function getNavigator(): HierarchyNavigator
    {
        return $this->navigator;
    }

    /**
     * Ingest and materialize star schema data into denormalized OLAP cube cell records.
     */
    public function materialize(): void
    {
        $factTable = $this->starSchema->getFactTable($this->factTableName);
        if ($factTable === null) {
            return;
        }

        $this->dataset = [];
        $chunks = $factTable->getColumnStore()->getChunks();

        foreach ($chunks as $chunk) {
            $rowCount = $chunk->getRowCount();
            $vectors = $chunk->getVectors();

            for ($i = 0; $i < $rowCount; $i++) {
                $rawFact = [];
                foreach ($vectors as $colName => $vector) {
                    $rawFact[$colName] = $vector->get($i);
                }

                $denormalizedRow = $this->starSchema->denormalizeFact($this->factTableName, $rawFact);
                $this->dataset[] = $denormalizedRow;
            }
        }
    }

    /**
     * Slice cube along a single dimension.
     *
     * @param string $dimension (e.g. 'dim_date.year', 'dim_customer.country')
     * @param mixed $value
     * @return static
     */
    public function slice(string $dimension, mixed $value): static
    {
        $clone = clone $this;
        $clone->activeFilters[$dimension] = $value;
        return $clone;
    }

    /**
     * Dice cube along multiple dimensions with multiple values.
     *
     * @param array<string, mixed> $predicates
     * @return static
     */
    public function dice(array $predicates): static
    {
        $clone = clone $this;
        foreach ($predicates as $dim => $val) {
            $clone->activeFilters[$dim] = $val;
        }
        return $clone;
    }

    /**
     * Drill down into a deeper level of a dimension.
     */
    public function drillDown(string $dimension, string $level): static
    {
        $clone = clone $this;
        $clone->activeLevels[$dimension] = $level;
        return $clone;
    }

    /**
     * Roll up to a higher level of a dimension.
     */
    public function rollUp(string $dimension, string $level): static
    {
        $clone = clone $this;
        $clone->activeLevels[$dimension] = $level;
        return $clone;
    }

    /**
     * Filter and retrieve matching dataset as CubeSlice.
     */
    public function getSlice(): CubeSlice
    {
        $filtered = [];

        foreach ($this->dataset as $row) {
            $match = true;
            foreach ($this->activeFilters as $dimKey => $targetVal) {
                $rowVal = $row[$dimKey] ?? null;
                if (is_array($targetVal)) {
                    if (!in_array($rowVal, $targetVal, false)) {
                        $match = false;
                        break;
                    }
                } else {
                    if ($rowVal != $targetVal) {
                        $match = false;
                        break;
                    }
                }
            }

            if ($match) {
                $filtered[] = $row;
            }
        }

        return new CubeSlice($this->name, $this->activeFilters, $filtered, $this->measures);
    }

    /**
     * Pivot cube into cross-tabulation matrix.
     *
     * @param array<string> $rowDimensions
     * @param array<string> $colDimensions
     * @param array<string, string> $measures
     * @return array<string, mixed>
     */
    public function pivot(array $rowDimensions, array $colDimensions, array $measures = []): array
    {
        if (empty($measures)) {
            $measures = $this->measures;
        }

        $slice = $this->getSlice();
        $data = $slice->getData();

        $matrix = [];
        $uniqueColKeys = [];

        foreach ($data as $row) {
            // Compute Row Key
            $rowParts = [];
            foreach ($rowDimensions as $rDim) {
                $rowParts[] = $row[$rDim] ?? '__NULL__';
            }
            $rowKey = empty($rowParts) ? '__TOTAL__' : implode(' - ', $rowParts);

            // Compute Col Key
            $colParts = [];
            foreach ($colDimensions as $cDim) {
                $colParts[] = $row[$cDim] ?? '__NULL__';
            }
            $colKey = empty($colParts) ? '__TOTAL__' : implode(' - ', $colParts);

            $uniqueColKeys[$colKey] = true;

            if (!isset($matrix[$rowKey])) {
                $matrix[$rowKey] = [];
            }
            if (!isset($matrix[$rowKey][$colKey])) {
                $matrix[$rowKey][$colKey] = [];
                foreach ($measures as $mName => $aggFn) {
                    $matrix[$rowKey][$colKey][$mName] = [
                        'sum' => 0.0,
                        'count' => 0,
                        'min' => null,
                        'max' => null,
                        'fn' => strtoupper($aggFn),
                    ];
                }
            }

            foreach ($measures as $mName => $aggFn) {
                $val = (float)($row[$mName] ?? 0.0);
                $cell = &$matrix[$rowKey][$colKey][$mName];
                $cell['sum'] += $val;
                $cell['count']++;
                if ($cell['min'] === null || $val < $cell['min']) {
                    $cell['min'] = $val;
                }
                if ($cell['max'] === null || $val > $cell['max']) {
                    $cell['max'] = $val;
                }
            }
        }

        // Finalize cell values
        $finalPivot = [];
        foreach ($matrix as $rKey => $cols) {
            $finalPivot[$rKey] = [];
            foreach ($cols as $cKey => $measList) {
                $finalPivot[$rKey][$cKey] = [];
                foreach ($measList as $mName => $agg) {
                    $fn = $agg['fn'];
                    $val = match ($fn) {
                        'SUM' => $agg['sum'],
                        'AVG', 'AVERAGE' => $agg['count'] > 0 ? round($agg['sum'] / $agg['count'], 4) : 0.0,
                        'MIN' => $agg['min'] ?? 0.0,
                        'MAX' => $agg['max'] ?? 0.0,
                        'COUNT' => $agg['count'],
                        default => $agg['sum'],
                    };
                    $finalPivot[$rKey][$cKey][$mName] = $val;
                }
            }
        }

        return [
            'rows' => $rowDimensions,
            'columns' => $colDimensions,
            'column_headers' => array_keys($uniqueColKeys),
            'matrix' => $finalPivot,
            'measures' => $measures,
            'total_cells' => count($finalPivot),
        ];
    }

    public function count(): int
    {
        return count($this->dataset);
    }
}
