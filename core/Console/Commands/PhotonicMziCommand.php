<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/PhotonicMziCommand.php
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
use NetFusion\Core\Photonic\Enums\MeshTopology;
use NetFusion\Core\Photonic\Mesh\InterferometerMesh;
use NetFusion\Core\Photonic\Physics\ComplexNumber;

class PhotonicMziCommand extends Command
{
    protected string $signature = 'photonic:mzi {ports=4 : Number of optical ports} {--topology=clements : MZI grid topology}';
    protected string $description = 'Simulate coherent optical propagation and transfer matrix across N-port MZI mesh';

    public function handle(): int
    {
        $ports = (int) ($this->argument(0) ?: 4);
        $topoName = strtolower((string) ($this->option('topology') ?: 'clements'));
        $topology = match ($topoName) {
            'reck' => MeshTopology::RECK,
            default => MeshTopology::CLEMENTS,
        };

        $mesh = new InterferometerMesh('mesh_cli', $ports, $topology);
        $cellCount = $mesh->getCellCount();

        // Propagate an optical impulse at port 0
        $input = array_fill(0, $ports, ComplexNumber::zero());
        $input[0] = ComplexNumber::one();

        $output = $mesh->propagate($input);

        $this->info("=== Coherent Photonic Interferometer Mesh ===");
        $this->line("Optical Ports      : {$ports}");
        $this->line("Mesh Topology      : " . strtoupper($topology->value));
        $this->line("Optical Depth      : " . $topology->opticalDepth($ports));
        $this->line("Total MZI Cells    : {$cellCount}");
        $this->line("Input Field Port 0 : 1.0000 + 0.0000i (Power = 1.00)");

        $this->line("\n--- Optical Output Field Distribution ---");
        for ($i = 0; $i < $ports; $i++) {
            $power = round($output[$i]->magnitudeSquared(), 4);
            $this->line("Port {$i} : " . (string)$output[$i] . " (Power = {$power})");
        }

        return 0;
    }
}
