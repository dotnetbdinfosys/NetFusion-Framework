<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/PhotonicGemmCommand.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Photonic\Accelerator\PhotonicGemmEngine;
use NetFusion\Core\Photonic\Enums\LaserWavelength;
use NetFusion\Core\Photonic\Enums\OpticalActivation;

class PhotonicGemmCommand extends Command
{
    protected string $signature = 'photonic:gemm {--dim=4 : Tensor dimension N} {--act=relu : Non-linear optical activation}';
    protected string $description = 'Execute speed-of-light optical tensor matrix-vector multiplication (Optical GEMV)';

    public function handle(): int
    {
        $dim = (int) ($this->option('dim') ?: 4);
        $actName = strtolower((string) ($this->option('act') ?: 'relu'));
        $activation = match ($actName) {
            'sigmoid' => OpticalActivation::OPTICAL_SIGMOID,
            'absorber' => OpticalActivation::SATURABLE_ABSORBER,
            'linear' => OpticalActivation::LINEAR,
            default => OpticalActivation::ELECTRO_OPTIC_RELU,
        };

        $engine = new PhotonicGemmEngine($dim, LaserWavelength::C_BAND_1550NM);

        // Generate synthetic weight matrix W
        $weights = [];
        for ($i = 0; $i < $dim; $i++) {
            $weights[$i] = [];
            for ($j = 0; $j < $dim; $j++) {
                $weights[$i][$j] = ($i === $j) ? 1.5 : 0.5;
            }
        }
        $engine->loadWeights($weights);

        $vector = array_fill(0, $dim, 1.0);
        $res = $engine->forward($vector, $activation);

        $this->info("=== Photonic Tensor GEMV Execution Complete ===");
        $this->line("Tensor Dimension   : {$dim} x {$dim}");
        $this->line("Laser Carrier      : 1550nm C-Band (193.41 THz)");
        $this->line("Non-Linearity      : " . strtoupper($activation->value));
        $this->line("Propagation Delay  : " . $res['latencyPicoseconds'] . " ps (Speed of Light)");
        $this->line("Energy Consumption : " . $res['energyFemtojoules'] . " fJ");
        $this->line("Energy Efficiency  : " . $res['topsPerWatt'] . " TOPS/Watt");
        $this->line("Output Tensor      : [" . implode(', ', $res['output']) . "]");

        return 0;
    }
}
