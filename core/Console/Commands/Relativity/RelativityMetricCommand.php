<?php
/**
 * NetFusion Framework - Enterprise Numerical Relativity CLI
 *
 * Command to compute spacetime metric tensor and event horizon geometries.
 *
 * @package    NetFusion\Core\Console\Commands\Relativity
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Relativity;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Relativity\Enums\MetricType;
use NetFusion\Core\Relativity\RelativityManager;
use NetFusion\Core\Relativity\Tensor\MetricTensor;

class RelativityMetricCommand extends Command
{
    protected string $signature = 'relativity:metric
        {--type=schwarzschild : Metric type (minkowski, schwarzschild, kerr)}
        {--mass=10.0 : Black hole mass in solar masses}
        {--radius=100.0 : Radial coordinate r in km}
        {--spin=0.7 : Kerr dimensionless spin parameter a*}';

    protected string $description = 'Compute 4D spacetime metric tensor components, determinant, and event horizon';

    public function handle(): int
    {
        $manager = relativity();
        $typeStr = (string) $this->option('type');

        $mass = (float) $this->option('mass');
        $radiusKm = (float) $this->option('radius');
        $spin = (float) $this->option('spin');

        $type = MetricType::tryFrom($typeStr) ?? MetricType::Schwarzschild;
        $radiusMeters = $radiusKm * 1000.0;

        $rsMeters = MetricTensor::schwarzschildRadiusMeters($mass);
        $metric = $manager->createMetric($type, $mass, $radiusMeters, $spin);

        $this->info("=== 4D Spacetime Metric Tensor [{$type->value}] ===");
        $this->line("Black Hole Mass : {$mass} M☉");
        $this->line("Schwarzschild rs: " . round($rsMeters / 1000.0, 3) . " km");
        $this->line("Evaluation r    : {$radiusKm} km");
        $this->line("Determinant det : " . sprintf("%.4e", $metric->determinant()));
        $this->line("Metric g_00 (tt): " . sprintf("%.6f", $metric->get(0, 0)));
        $this->line("Metric g_11 (rr): " . sprintf("%.6f", $metric->get(1, 1)));
        $this->line("Metric g_22 (θθ): " . sprintf("%.4e", $metric->get(2, 2)));
        $this->line("Metric g_33 (φφ): " . sprintf("%.4e", $metric->get(3, 3)));

        return 0;
    }
}
