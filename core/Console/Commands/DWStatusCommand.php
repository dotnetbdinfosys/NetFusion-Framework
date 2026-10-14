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

class DWStatusCommand extends Command
{
    protected string $signature = 'dw:status';
    protected string $description = 'Display Data Warehouse schemas, OLAP cubes and columnar storage metrics';

    public function handle(): int
    {
        $this->info("========================================================================");
        $this->info("  NetFusion Enterprise Data Warehouse & OLAP Storage Status");
        $this->info("========================================================================");

        /** @var DataWarehouseManager $dw */
        $dw = function_exists('app') && app()->has('datawarehouse') ? app('datawarehouse') : new DataWarehouseManager();

        $cubes = $dw->getCubes();
        $this->line("Registered Cubes: " . count($cubes));

        foreach ($cubes as $name => $cube) {
            $this->line("  - [Cube: {$name}] Records: {$cube->count()}");
        }

        return 0;
    }
}
