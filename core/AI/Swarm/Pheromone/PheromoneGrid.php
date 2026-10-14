<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Swarm/Pheromone/PheromoneGrid.php
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
 * 2D Spatial Stigmergic Pheromone Grid Matrix.
 *
 * Implements environmental stigmergy via spatial pheromone deposition,
 * localized gradient sampling, and exponential decay.
 */
class PheromoneGrid
{
    /** @var array<string, array<int, array<int, float>>> */
    private array $grid = [];

    public function __construct(
        private readonly int $width = 20,
        private readonly int $height = 20,
        private readonly float $maxConcentration = 100.0
    ) {
        $this->initializeGrid();
    }

    private function initializeGrid(): void
    {
        foreach (PheromoneType::cases() as $type) {
            $this->grid[$type->value] = [];
            for ($x = 0; $x < $this->width; $x++) {
                $this->grid[$type->value][$x] = array_fill(0, $this->height, 0.0);
            }
        }
    }

    /**
     * Deposit pheromone concentration at coordinates (x, y).
     */
    public function deposit(int $x, int $y, PheromoneType $type, float $amount): void
    {
        $x = max(0, min($this->width - 1, $x));
        $y = max(0, min($this->height - 1, $y));

        $current = $this->grid[$type->value][$x][$y];
        $this->grid[$type->value][$x][$y] = min($this->maxConcentration, $current + $amount);
    }

    /**
     * Get pheromone concentration at coordinates (x, y).
     */
    public function get(int $x, int $y, PheromoneType $type): float
    {
        $x = max(0, min($this->width - 1, $x));
        $y = max(0, min($this->height - 1, $y));

        return $this->grid[$type->value][$x][$y] ?? 0.0;
    }

    /**
     * Apply natural exponential decay across all grid cells.
     *
     * C_t = max(0, C_{t-1} * (1 - rate * decayMultiplier))
     */
    public function evaporate(float $baseRate = 0.05): void
    {
        foreach (PheromoneType::cases() as $type) {
            $effectiveRate = min(1.0, $baseRate * $type->decayMultiplier());
            $retentionFactor = 1.0 - $effectiveRate;

            for ($x = 0; $x < $this->width; $x++) {
                for ($y = 0; $y < $this->height; $y++) {
                    $this->grid[$type->value][$x][$y] = round($this->grid[$type->value][$x][$y] * $retentionFactor, 4);
                }
            }
        }
    }

    /**
     * Locate neighboring cell (Moore 8-neighborhood) with highest gradient concentration.
     *
     * @return array{x: int, y: int, value: float}
     */
    public function getHighestGradient(int $x, int $y, PheromoneType $type): array
    {
        $bestX = $x;
        $bestY = $y;
        $highest = $this->get($x, $y, $type);

        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                if ($dx === 0 && $dy === 0) {
                    continue;
                }
                $nx = max(0, min($this->width - 1, $x + $dx));
                $ny = max(0, min($this->height - 1, $y + $dy));
                $val = $this->grid[$type->value][$nx][$ny];

                if ($val > $highest) {
                    $highest = $val;
                    $bestX = $nx;
                    $bestY = $ny;
                }
            }
        }

        return ['x' => $bestX, 'y' => $bestY, 'value' => $highest];
    }

    /**
     * Calculate aggregate pheromone mass across grid.
     */
    public function getTotalConcentration(PheromoneType $type): float
    {
        $total = 0.0;
        for ($x = 0; $x < $this->width; $x++) {
            $total += array_sum($this->grid[$type->value][$x]);
        }
        return round($total, 4);
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function reset(): void
    {
        $this->initializeGrid();
    }
}
