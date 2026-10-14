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

namespace NetFusion\Core\DataWarehouse\BI;

use NetFusion\Core\DataWarehouse\Cube\OLAPCube;

class PivotTable
{
    /**
     * @var array<string, CalculatedMeasure>
     */
    protected array $calculatedMeasures = [];

    /**
     * @param OLAPCube $cube
     * @param array<string> $rowDimensions
     * @param array<string> $colDimensions
     * @param array<string, string> $measures
     */
    public function __construct(
        protected OLAPCube $cube,
        protected array $rowDimensions = [],
        protected array $colDimensions = [],
        protected array $measures = []
    ) {
    }

    public function setRows(array $dimensions): static
    {
        $this->rowDimensions = $dimensions;
        return $this;
    }

    public function setColumns(array $dimensions): static
    {
        $this->colDimensions = $dimensions;
        return $this;
    }

    public function setMeasures(array $measures): static
    {
        $this->measures = $measures;
        return $this;
    }

    public function addCalculatedMeasure(CalculatedMeasure $calc): static
    {
        $this->calculatedMeasures[$calc->getName()] = $calc;
        return $this;
    }

    /**
     * Generate structured BI pivot cross-tabulation report.
     *
     * @return array{
     *     rows: array<string>,
     *     columns: array<string>,
     *     headers: array<string>,
     *     records: array<string, array<string, array<string, mixed>>>,
     *     row_totals: array<string, array<string, float>>,
     *     column_totals: array<string, array<string, float>>,
     *     grand_totals: array<string, float>
     * }
     */
    public function generate(): array
    {
        $rawPivot = $this->cube->pivot($this->rowDimensions, $this->colDimensions, $this->measures);
        $matrix = $rawPivot['matrix'];
        $colHeaders = $rawPivot['column_headers'];

        $rowTotals = [];
        $colTotals = [];
        $grandTotals = [];

        // Initialize Grand Totals
        foreach ($this->measures as $mName => $fn) {
            $grandTotals[$mName] = 0.0;
        }

        // Compute Calculated Measures & Subtotals
        foreach ($matrix as $rKey => &$cols) {
            $rowTotals[$rKey] = [];
            foreach ($this->measures as $mName => $fn) {
                $rowTotals[$rKey][$mName] = 0.0;
            }

            foreach ($colHeaders as $cKey) {
                if (!isset($cols[$cKey])) {
                    $cols[$cKey] = [];
                    foreach ($this->measures as $mName => $fn) {
                        $cols[$cKey][$mName] = 0.0;
                    }
                }

                // Accumulate Row and Col totals
                foreach ($this->measures as $mName => $fn) {
                    $val = (float)($cols[$cKey][$mName] ?? 0.0);
                    $rowTotals[$rKey][$mName] += $val;

                    if (!isset($colTotals[$cKey][$mName])) {
                        $colTotals[$cKey][$mName] = 0.0;
                    }
                    $colTotals[$cKey][$mName] += $val;
                    $grandTotals[$mName] += $val;
                }

                // Apply Calculated Measures per cell
                foreach ($this->calculatedMeasures as $calcName => $calc) {
                    $cols[$cKey][$calcName] = $calc->compute($cols[$cKey]);
                }
            }

            // Apply Calculated Measures to Row Total
            foreach ($this->calculatedMeasures as $calcName => $calc) {
                $rowTotals[$rKey][$calcName] = $calc->compute($rowTotals[$rKey]);
            }
        }

        // Apply Calculated Measures to Column & Grand Totals
        foreach ($colHeaders as $cKey) {
            foreach ($this->calculatedMeasures as $calcName => $calc) {
                $colTotals[$cKey][$calcName] = $calc->compute($colTotals[$cKey] ?? []);
            }
        }
        foreach ($this->calculatedMeasures as $calcName => $calc) {
            $grandTotals[$calcName] = $calc->compute($grandTotals);
        }

        return [
            'rows' => $this->rowDimensions,
            'columns' => $this->colDimensions,
            'headers' => $colHeaders,
            'records' => $matrix,
            'row_totals' => $rowTotals,
            'column_totals' => $colTotals,
            'grand_totals' => $grandTotals,
        ];
    }
}
