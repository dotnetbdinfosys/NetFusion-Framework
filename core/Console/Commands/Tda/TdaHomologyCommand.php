<?php
/**
 * NetFusion Framework - Enterprise Topological Data Analysis CLI
 *
 * Command to compute persistent homology and Betti numbers from point clouds.
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

class TdaHomologyCommand extends Command
{
    protected string $signature = 'tda:homology
        {--points=circle : Point cloud geometry (circle, clusters, noisy_loop)}
        {--max-filtration=2.5 : Max filtration distance}
        {--dim=2 : Max homology dimension}
        {--metric=euclidean : Distance metric}';

    protected string $description = 'Compute persistent homology barcodes and Betti numbers from a point cloud manifold';

    public function handle(): int
    {
        $manager = tda();
        $shape = (string) $this->option('points');

        $maxFiltration = (float) $this->option('max-filtration');
        $maxDim = (int) $this->option('dim');
        $metricStr = (string) $this->option('metric');
        $metric = DistanceMetric::tryFrom($metricStr) ?? DistanceMetric::Euclidean;

        // Generate synthetic point cloud
        $points = match ($shape) {
            'clusters' => [
                [0.0, 0.0], [0.1, 0.1], [0.1, -0.1], // Cluster 1
                [5.0, 5.0], [5.1, 5.1], [5.2, 5.0], // Cluster 2
            ],
            default => [
                // 8 points on a circle (radius 1.0) -> prominent H1 loop
                [1.0, 0.0],
                [0.707, 0.707],
                [0.0, 1.0],
                [-0.707, 0.707],
                [-1.0, 0.0],
                [-0.707, -0.707],
                [0.0, -1.0],
                [0.707, -0.707],
            ],
        };

        $result = $manager->computePersistentHomology($points, $maxFiltration, $maxDim, $metric);

        $this->info("=== Topological Data Analysis: Persistent Homology [{$shape}] ===");
        $this->line("Point Count      : {$result['num_points']} points");
        $this->line("Total Simplices  : {$result['num_simplices']} simplices");
        $this->line("Betti β_0 (Clusters) : {$result['betti_numbers']['h0']}");
        $this->line("Betti β_1 (Loops)    : {$result['betti_numbers']['h1']}");
        $this->line("Betti β_2 (Cavities) : {$result['betti_numbers']['h2']}");
        $this->line("Total Barcode Intervals : " . count($result['intervals']));

        return 0;
    }
}
