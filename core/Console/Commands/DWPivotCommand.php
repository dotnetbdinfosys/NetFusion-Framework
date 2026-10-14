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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\DataWarehouse\DataWarehouseManager;
use NetFusion\Core\DataWarehouse\BI\PivotRenderer;

class DWPivotCommand extends Command
{
    protected string $signature = 'dw:pivot {cube} {--rows=} {--cols=} {--measures=}';
    protected string $description = 'Execute dynamic BI cross-tabulation pivot query on OLAP cube';

    public function handle(): int
    {
        $cubeName = (string)$this->argument('cube');
        $rowsArg = (string)($this->option('rows') ?? '');
        $colsArg = (string)($this->option('cols') ?? '');
        $measArg = (string)($this->option('measures') ?? '');

        /** @var DataWarehouseManager $dw */
        $dw = function_exists('app') && app()->has('datawarehouse') ? app('datawarehouse') : new DataWarehouseManager();
        $cube = $dw->getCube($cubeName);

        if ($cube === null) {
            $this->error("OLAP Cube [{$cubeName}] not found.");
            return 1;
        }

        $rows = array_filter(array_map('trim', explode(',', $rowsArg)));
        $cols = array_filter(array_map('trim', explode(',', $colsArg)));
        $measures = [];
        if (!empty($measArg)) {
            foreach (explode(',', $measArg) as $m) {
                $parts = explode(':', trim($m));
                $mName = $parts[0];
                $agg = $parts[1] ?? 'SUM';
                $measures[$mName] = $agg;
            }
        }

        $pivot = $dw->pivot($cubeName, $rows, $cols, $measures);
        $result = $pivot->generate();

        $this->info("BI Pivot Matrix Result for Cube [{$cubeName}]:");
        $this->line(PivotRenderer::toMarkdown($result));

        return 0;
    }
}
