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

class PivotRenderer
{
    /**
     * Render Pivot report to formatted Markdown table.
     */
    public static function toMarkdown(array $pivotData): string
    {
        $headers = $pivotData['headers'] ?? [];
        $records = $pivotData['records'] ?? [];
        $rowTotals = $pivotData['row_totals'] ?? [];
        $grandTotals = $pivotData['grand_totals'] ?? [];

        $lines = [];
        $headerCols = array_merge(['Dimension / Metric'], $headers, ['Total']);
        $lines[] = '| ' . implode(' | ', $headerCols) . ' |';
        $lines[] = '| ' . implode(' | ', array_map(fn() => '---', $headerCols)) . ' |';

        foreach ($records as $rKey => $cols) {
            $rowValues = [$rKey];
            foreach ($headers as $h) {
                $cell = $cols[$h] ?? [];
                $firstVal = self::firstValue($cell);
                $rowValues[] = is_numeric($firstVal) ? (string)$firstVal : '-';
            }
            $rTot = self::firstValue($rowTotals[$rKey] ?? []);
            $rowValues[] = is_numeric($rTot) ? (string)$rTot : '-';
            $lines[] = '| ' . implode(' | ', $rowValues) . ' |';
        }

        // Grand total line
        $gtValues = ['**Grand Total**'];
        foreach ($headers as $h) {
            $cTot = self::firstValue($pivotData['column_totals'][$h] ?? []);
            $gtValues[] = is_numeric($cTot) ? "**{$cTot}**" : '-';
        }
        $gt = self::firstValue($grandTotals);
        $gtValues[] = is_numeric($gt) ? "**{$gt}**" : '-';
        $lines[] = '| ' . implode(' | ', $gtValues) . ' |';

        return implode("\n", $lines);
    }

    /**
     * Render Pivot report to CSV format.
     */
    public static function toCsv(array $pivotData): string
    {
        $headers = $pivotData['headers'] ?? [];
        $records = $pivotData['records'] ?? [];
        $rowTotals = $pivotData['row_totals'] ?? [];

        $fp = fopen('php://memory', 'r+');
        fputcsv($fp, array_merge(['Row Dimension'], $headers, ['Total']));

        foreach ($records as $rKey => $cols) {
            $rowValues = [$rKey];
            foreach ($headers as $h) {
                $cell = $cols[$h] ?? [];
                $rowValues[] = self::firstValue($cell) ?? 0;
            }
            $rTot = self::firstValue($rowTotals[$rKey] ?? []);
            $rowValues[] = $rTot ?? 0;
            fputcsv($fp, $rowValues);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv !== false ? $csv : '';
    }

    /**
     * Render Pivot report as standalone HTML Table widget.
     */
    public static function toHtml(array $pivotData, string $title = 'BI Analytics Pivot Report'): string
    {
        $headers = $pivotData['headers'] ?? [];
        $records = $pivotData['records'] ?? [];
        $rowTotals = $pivotData['row_totals'] ?? [];
        $grandTotals = $pivotData['grand_totals'] ?? [];

        $html = '<div class="nf-pivot-container" style="font-family:sans-serif;margin:15px 0;">';
        $html .= '<h3 style="margin-bottom:8px;color:#1e293b;">' . htmlspecialchars($title) . '</h3>';
        $html .= '<table style="width:100%;border-collapse:collapse;text-align:left;font-size:14px;border:1px solid #cbd5e1;">';
        $html .= '<thead><tr style="background:#f1f5f9;border-bottom:2px solid #94a3b8;">';
        $html .= '<th style="padding:10px;border:1px solid #cbd5e1;">Dimension</th>';
        foreach ($headers as $h) {
            $html .= '<th style="padding:10px;border:1px solid #cbd5e1;text-align:right;">' . htmlspecialchars((string)$h) . '</th>';
        }
        $html .= '<th style="padding:10px;border:1px solid #cbd5e1;text-align:right;background:#e2e8f0;">Total</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($records as $rKey => $cols) {
            $html .= '<tr><td style="padding:8px 10px;border:1px solid #cbd5e1;font-weight:600;">' . htmlspecialchars($rKey) . '</td>';
            foreach ($headers as $h) {
                $cell = $cols[$h] ?? [];
                $firstVal = self::firstValue($cell);
                $html .= '<td style="padding:8px 10px;border:1px solid #cbd5e1;text-align:right;">' . htmlspecialchars((string)$firstVal) . '</td>';
            }
            $rTot = self::firstValue($rowTotals[$rKey] ?? []);
            $html .= '<td style="padding:8px 10px;border:1px solid #cbd5e1;text-align:right;font-weight:600;background:#f8fafc;">' . htmlspecialchars((string)$rTot) . '</td></tr>';
        }

        $html .= '</tbody><tfoot><tr style="background:#f1f5f9;font-weight:bold;border-top:2px solid #94a3b8;">';
        $html .= '<td style="padding:10px;border:1px solid #cbd5e1;">Grand Total</td>';
        foreach ($headers as $h) {
            $cTot = self::firstValue($pivotData['column_totals'][$h] ?? []);
            $html .= '<td style="padding:10px;border:1px solid #cbd5e1;text-align:right;">' . htmlspecialchars((string)$cTot) . '</td>';
        }
        $gt = self::firstValue($grandTotals);
        $html .= '<td style="padding:10px;border:1px solid #cbd5e1;text-align:right;background:#e2e8f0;">' . htmlspecialchars((string)$gt) . '</td>';
        $html .= '</tr></tfoot></table></div>';

        return $html;
    }

    protected static function firstValue(array $array): mixed
    {
        if (empty($array)) {
            return null;
        }
        return reset($array);
    }
}
