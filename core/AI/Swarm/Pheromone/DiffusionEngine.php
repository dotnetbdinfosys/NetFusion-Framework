<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Swarm/Pheromone/DiffusionEngine.php
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

namespace NetFusion\Core\AI\Swarm\Pheromone;

use NetFusion\Core\AI\Swarm\Enums\PheromoneType;

/**
 * 2D Spatial Diffusion Kernel Engine (Fick's Second Law).
 *
 * Spatially diffuses pheromone gradients across adjacent cells:
 *   dC/dt = D * nabla^2 C
 */
class DiffusionEngine
{
    /**
     * Diffuse pheromone matrix across grid cells.
     *
     * @param PheromoneGrid $grid Target grid
     * @param PheromoneType $type Pheromone channel to diffuse
     * @param float $diffusionRate Fraction of mass spreading to neighboring cells (0.0 to 0.5)
     */
    public function diffuse(PheromoneGrid $grid, PheromoneType $type, float $diffusionRate = 0.1): void
    {
        $w = $grid->getWidth();
        $h = $grid->getHeight();
        $delta = [];

        for ($x = 0; $x < $w; $x++) {
            $delta[$x] = array_fill(0, $h, 0.0);
        }

        // Discrete 4-point Laplacian kernel
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $conc = $grid->get($x, $y, $type);
                if ($conc <= 0.001) {
                    continue;
                }

                $spreadTotal = $conc * $diffusionRate;
                $neighbors = 0;

                // Check valid 4-neighbors
                $coords = [
                    [$x - 1, $y],
                    [$x + 1, $y],
                    [$x, $y - 1],
                    [$x, $y + 1],
                ];

                $validNeighbors = [];
                foreach ($coords as [$nx, $ny]) {
                    if ($nx >= 0 && $nx < $w && $ny >= 0 && $ny < $h) {
                        $validNeighbors[] = [$nx, $ny];
                    }
                }

                $neighbors = count($validNeighbors);
                if ($neighbors > 0) {
                    $share = $spreadTotal / $neighbors;
                    $delta[$x][$y] -= $spreadTotal;
                    foreach ($validNeighbors as [$nx, $ny]) {
                        $delta[$nx][$ny] += $share;
                    }
                }
            }
        }

        // Apply diffusion delta
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                if (abs($delta[$x][$y]) > 1e-6) {
                    $newVal = max(0.0, $grid->get($x, $y, $type) + $delta[$x][$y]);
                    $grid->deposit($x, $y, $type, $newVal - $grid->get($x, $y, $type));
                }
            }
        }
    }
}
