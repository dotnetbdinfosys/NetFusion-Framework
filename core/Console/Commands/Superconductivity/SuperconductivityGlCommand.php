<?php
/**
 * NetFusion Framework - Enterprise Superconductivity CLI
 *
 * Command to analyze Ginzburg-Landau field parameters, coherence length, and critical fields.
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
use NetFusion\Core\Physics\Superconductivity\Enums\SuperconductorMaterial;

class SuperconductivityGlCommand extends Command
{
    protected string $name = 'supercond:gl';

    protected string $signature = 'supercond:gl {material? : Superconductor material (lead, nbti, nb3sn, mgb2, ybco, bscco)} {--temp=77.0 : Temperature in K} {--field=1.0 : Magnetic field in Tesla}';

    protected string $description = 'Compute Ginzburg-Landau macroscopic quantum parameters, coherence length, and critical fields';

    public function handle(): int
    {
        $manager = supercond();

        $matStr = (string) ($this->argument(0) ?: 'ybco');
        $tempK  = (float) ($this->option('temp') ?: 77.0);
        $fieldT = (float) ($this->option('field') ?: 1.0);

        $mat = SuperconductorMaterial::tryFrom(strtolower($matStr)) ?? SuperconductorMaterial::YBCO;

        $state = $manager->evaluateState($mat, $tempK, $fieldT);

        $this->info("=== Ginzburg-Landau Superconducting State Analysis ===");
        $this->line(sprintf("Material Name        : %s (Type: %s)", strtoupper($mat->value), $state['type']));
        $this->line(sprintf("High-Tc (Liquid N2)  : %s", $mat->isHighTemperature() ? 'YES (Tc > 77 K)' : 'NO (Requires Liquid Helium 4.2 K)'));
        $this->line(sprintf("Critical Temp (T_c)  : %.2f K", $state['tc_k']));
        $this->line(sprintf("Operating Temp (T)   : %.2f K (Reduced t = %.3f)", $tempK, $tempK / $state['tc_k']));
        $this->line(sprintf("Applied Field (B)    : %.4f Tesla", $fieldT));
        $this->line("----------------------------------------------------------");
        $this->info("=== Macroscopic Quantum Length Scales & Critical Fields ===");
        $this->line(sprintf("Coherence Length xi(T) : %8.2f nm", $state['coherence_length_nm']));
        $this->line(sprintf("Penetration Depth l(T) : %8.2f nm", $state['penetration_depth_nm']));
        $this->line(sprintf("G-L Parameter kappa    : %8.2f (%s)", $state['kappa'], $state['kappa'] > 0.7071 ? 'kappa > 1/sqrt(2) [Type-II]' : 'Type-I'));
        $this->line(sprintf("Lower Critical Field H_c1: %8.4f Tesla", $state['bc1_t']));
        $this->line(sprintf("Upper Critical Field H_c2: %8.4f Tesla", $state['bc2_t']));
        $this->line("----------------------------------------------------------");
        $this->info(sprintf("Thermodynamic State    : %s", $state['state_regime']));
        $this->line(sprintf("Superconductivity Active: %s", $state['is_superconducting'] ? 'YES [Active Zero-Resistance]' : 'NO [Quenched / Normal Resistive]'));
        $this->line("----------------------------------------------------------");

        return 0;
    }
}
