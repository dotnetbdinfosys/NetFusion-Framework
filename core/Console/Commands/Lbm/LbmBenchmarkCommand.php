<?php
/**
 * NetFusion Framework - Enterprise Computational Fluid Dynamics CLI
 *
 * Command to benchmark Lattice Boltzmann execution speed (MLUPS).
 *
 * @package    NetFusion\Core\Console\Commands\Lbm
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Lbm;

use NetFusion\Core\Console\Command;

class LbmBenchmarkCommand extends Command
{
    protected string $name = 'lbm:benchmark';

    protected string $signature = 'lbm:benchmark {--size=40 : Mesh dimension} {--steps=25 : Simulation iterations}';

    protected string $description = 'Benchmark LBM throughput in Million Lattice Updates Per Second (MLUPS)';

    public function handle(): int
    {
        $manager = lbm();

        $size  = (int) ($this->option('size') ?: 40);
        $steps = (int) ($this->option('steps') ?: 25);

        $this->info("Benchmarking NetFusion LBM Engine Throughput...");
        $res = $manager->benchmark($size, $size, $steps);

        $this->line("--------------------------------------------------");
        $this->line(sprintf("Lattice Dimension : %dx%d (%d cells)", $res['nx'], $res['ny'], $res['nx'] * $res['ny']));
        $this->line(sprintf("Iteration Steps   : %d", $res['steps']));
        $this->line(sprintf("Wall Clock Time   : %.4f seconds", $res['elapsed_sec']));
        $this->line(sprintf("Throughput Density: \033[32m%.4f MLUPS\033[0m (Million Lattice Updates/sec)", $res['mlups']));
        $this->line("--------------------------------------------------");

        return 0;
    }
}
