<?php
/**
 * NetFusion Framework - Enterprise Superconductivity CLI
 *
 * Command to model Abrikosov vortex lattice, quantized flux lines, and pinning forces.
 *
 * @package    NetFusion\Core\Console\Commands\Superconductivity
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Superconductivity;

use NetFusion\Core\Console\Command;

class SuperconductivityVortexCommand extends Command
{
    protected string $name = 'supercond:vortex';

    protected string $signature = 'supercond:vortex {--field=2.0 : Magnetic field in Tesla} {--current=1e9 : Transport current density J in A/m^2} {--pinning=1e9 : Pinning force density in N/m^3}';

    protected string $description = 'Model Abrikosov triangular vortex lattice, flux quantization, and pinning dynamics';

    public function handle(): int
    {
        $manager = supercond();
        $lattice = $manager->getVortexLattice();

        $fieldT = (float) ($this->option('field') ?: 2.0);
        $currJ  = (float) ($this->option('current') ?: 1.0e9);
        $pinF   = (float) ($this->option('pinning') ?: 1.0e9);

        $res = $lattice->evaluateVortexPinning($fieldT, $currJ, $pinF);

        $this->info("=== Abrikosov Triangular Flux Vortex Lattice Analysis ===");
        $this->line(sprintf("Applied Magnetic Field (B)  : %.2f Tesla", $fieldT));
        $this->line(sprintf("Transport Current Density (J): %.2e A/m^2 (%.2f MA/cm^2)", $currJ, $currJ / 1e10));
        $this->line(sprintf("Flux Quantum (Phi_0)        : 2.0678 x 10^-15 Wb (h / 2e)"));
        $this->line("----------------------------------------------------------");
        $this->line(sprintf("Areal Vortex Line Density   : %.2e vortices/m^2 (%.2e per mm^2)", $res['vortex_density_m2'], $res['vortex_density_m2'] / 1e6));
        $this->line(sprintf("Triangular Lattice Spacing  : %.2f nm (vortex core separation)", $res['lattice_spacing_nm']));
        $this->line("----------------------------------------------------------");
        $this->info("=== Vortex Depinning & Lorentz Force Balance ===");
        $this->line(sprintf("Lorentz Driving Force (F_L) : %.2e N/m^3 (J x B)", $res['lorentz_force_n_m3']));
        $this->line(sprintf("Critical Pinning Force (F_p): %.2e N/m^3", $res['pinning_force_n_m3']));
        $this->info(sprintf("Vortex Pinning State        : %s", $res['is_pinned'] ? 'PINNED [Zero Dissipation, Supercurrent Flow]' : 'DEPINNED [Flux Flow Dissipation, Resisitive Voltage]'));
        $this->line("----------------------------------------------------------");

        return 0;
    }
}
