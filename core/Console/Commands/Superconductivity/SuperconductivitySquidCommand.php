<?php
/**
 * NetFusion Framework - Enterprise Superconductivity CLI
 *
 * Command to model DC-SQUID macroscopic quantum interference and magnetic flux sensitivity.
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
use NetFusion\Core\Physics\Superconductivity\GinzburgLandau\GinzburgLandauEngine;

class SuperconductivitySquidCommand extends Command
{
    protected string $name = 'supercond:squid';

    protected string $signature = 'supercond:squid {--field=1e-6 : Applied magnetic field in Tesla} {--area=1e-8 : SQUID loop area in m^2} {--bias=22e-6 : Bias current in A}';

    protected string $description = 'Simulate DC-SQUID quantum interference, critical current modulation, and voltage response';

    public function handle(): int
    {
        $manager = supercond();

        $fieldT = (float) ($this->option('field') ?: 1.0e-6);
        $areaM2 = (float) ($this->option('area') ?: 1.0e-8);
        $biasA  = (float) ($this->option('bias') ?: 22.0e-6);

        $squid = $manager->createSquid($areaM2);

        $fluxWb = $squid->computeMagneticFlux($fieldT);
        $fluxQuanta = $fluxWb / GinzburgLandauEngine::FLUX_QUANTUM;

        $maxIc = $squid->getMaximumSupercurrent($fluxWb);
        $volt  = $squid->getOutputVoltage($fluxWb, $biasA);
        $transferSensitivity = $squid->getTransferFunction($biasA);

        $this->info("=== DC-SQUID Superconducting Quantum Magnetometer Simulation ===");
        $this->line(sprintf("External Magnetic Field (B) : %.2e Tesla (%.2f uT)", $fieldT, $fieldT * 1e6));
        $this->line(sprintf("SQUID Pick-Up Loop Area     : %.2e m^2 (%.2f um x %.2f um)", $areaM2, sqrt($areaM2) * 1e6, sqrt($areaM2) * 1e6));
        $this->line(sprintf("Screening Parameter (beta_L): %.3f", $squid->getScreeningParameter()));
        $this->line("----------------------------------------------------------");
        $this->line(sprintf("Magnetic Flux Enclosed (Phi): %.4e Wb", $fluxWb));
        $this->line(sprintf("Flux Modulation Factor      : %.4f Phi_0 (Modulo: %.4f)", $fluxQuanta, fmod($fluxQuanta, 1.0)));
        $this->line(sprintf("Interference Supercurrent Ic: %8.2f uA", $maxIc * 1e6));
        $this->line(sprintf("Operating Bias Current (Ib) : %8.2f uA", $biasA * 1e6));
        $this->info(sprintf("SQUID Output Voltage V(Phi) : %8.3f uV", $volt * 1e6));
        $this->line(sprintf("Flux-to-Voltage Sensitivity : %8.2f uV / Phi_0", $transferSensitivity * GinzburgLandauEngine::FLUX_QUANTUM * 1e6));
        $this->line("----------------------------------------------------------");

        return 0;
    }
}
