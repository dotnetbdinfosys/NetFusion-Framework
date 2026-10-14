<?php
/**
 * NetFusion Framework - Enterprise Topological Data Analysis CLI
 *
 * Command to display persistent homology barcodes in terminal ASCII.
 *
 * @package    NetFusion\Core\Console\Commands\Tda
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Tda;

use NetFusion\Core\Analytics\Tda\TdaManager;
use NetFusion\Core\Console\Command;

class TdaBarcodeCommand extends Command
{
    protected string $signature = 'tda:barcode
        {--max-filtration=2.0 : Maximum filtration radius}';

    protected string $description = 'Render topological persistence barcode intervals and feature lifespans';

    public function handle(): int
    {
        $manager = tda();
        $maxFiltration = (float) $this->option('max-filtration');


        // Simple ring topology
        $points = [
            [1.0, 0.0],
            [0.5, 0.866],
            [-0.5, 0.866],
            [-1.0, 0.0],
            [-0.5, -0.866],
            [0.5, -0.866],
        ];

        $res = $manager->computePersistentHomology($points, $maxFiltration, 1);

        $this->info("=== Topological Barcode Persistence Intervals ===");
        $this->line(sprintf("%-5s | %-8s | %-8s | %-10s | %s", "Dim", "Birth", "Death", "Lifespan", "Barcode"));
        $this->line(str_repeat('-', 55));

        foreach ($res['intervals'] as $int) {
            $deathStr = ($int['death'] !== null) ? (string) $int['death'] : 'INF';
            $lifeStr = ($int['infinite']) ? 'INF' : (string) $int['lifespan'];

            // ASCII barcode visualization
            $barLen = ($int['infinite']) ? 15 : (int) min(20, max(2, round(($int['lifespan'] / $maxFiltration) * 20)));
            $bar = str_repeat('=', $barLen) . ($int['infinite'] ? '>' : '|');

            $this->line(sprintf("H%-4d | %-8.4f | %-8s | %-10s | %s", $int['dimension'], $int['birth'], $deathStr, $lifeStr, $bar));
        }

        return 0;
    }
}
