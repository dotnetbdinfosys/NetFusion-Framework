<?php
/**
 * NetFusion Framework - Enterprise Topological Data Analysis CLI
 *
 * Command to inspect Vietoris-Rips filtered simplicial complex breakdown.
 *
 * @package    NetFusion\Core\Console\Commands\Tda
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Tda;

use NetFusion\Core\Analytics\Tda\Enums\DistanceMetric;
use NetFusion\Core\Analytics\Tda\TdaManager;
use NetFusion\Core\Console\Command;

class TdaRipsCommand extends Command
{
    protected string $signature = 'tda:rips
        {--max-filtration=1.5 : Filtration radius}
        {--dim=2 : Max dimension}';

    protected string $description = 'Construct and inspect Vietoris-Rips simplicial complex statistics';

    public function handle(): int
    {
        $manager = tda();
        $maxFiltration = (float) $this->option('max-filtration');

        $maxDim = (int) $this->option('dim');

        // Tetrahedron point cloud
        $points = [
            [0.0, 0.0, 0.0],
            [1.0, 0.0, 0.0],
            [0.5, 0.866, 0.0],
            [0.5, 0.288, 0.816],
        ];

        $complex = $manager->buildRipsComplex($points, $maxFiltration, $maxDim, DistanceMetric::Euclidean);
        $simplices = $complex->getSimplices();

        $dimCounts = [0 => 0, 1 => 0, 2 => 0];
        foreach ($simplices as $s) {
            $d = $s->getDimension();
            $dimCounts[$d] = ($dimCounts[$d] ?? 0) + 1;
        }

        $this->info("=== Vietoris-Rips Simplicial Complex Statistics ===");
        $this->line("Filtration Radius (ε): {$maxFiltration}");
        $this->line("Total Simplices      : " . count($simplices));
        $this->line("  0-Simplices (Vertices)  : {$dimCounts[0]}");
        $this->line("  1-Simplices (Edges)     : {$dimCounts[1]}");
        $this->line("  2-Simplices (Triangles) : {$dimCounts[2]}");

        return 0;
    }
}
