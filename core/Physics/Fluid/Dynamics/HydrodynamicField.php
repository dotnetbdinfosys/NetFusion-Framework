<?php
/**
 * NetFusion Framework - Enterprise Computational Fluid Dynamics (CFD / LBM) Fabric
 *
 * Macroscopic hydrodynamic field extractor computing density, velocity, and vorticity curl.
 *
 * @package    NetFusion\Core\Physics\Fluid\Dynamics
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Fluid\Dynamics;

use NetFusion\Core\Physics\Fluid\Lattice\LbmGrid;

class HydrodynamicField
{
    /**
     * @param int $nx
     * @param int $ny
     * @param array<int, array<int, float>> $density
     * @param array<int, array<int, float>> $ux
     * @param array<int, array<int, float>> $uy
     * @param array<int, array<int, float>> $speed
     * @param array<int, array<int, float>> $vorticity
     * @param array<int, array<int, bool>>  $solidMask
     */
    public function __construct(
        protected readonly int $nx,
        protected readonly int $ny,
        protected readonly array $density,
        protected readonly array $ux,
        protected readonly array $uy,
        protected readonly array $speed,
        protected readonly array $vorticity,
        protected readonly array $solidMask
    ) {
    }

    /**
     * Extract macroscopic state and calculate vorticity field from grid.
     */
    public static function fromGrid(LbmGrid $grid): self
    {
        $nx = $grid->getNx();
        $ny = $grid->getNy();

        $density = [];
        $ux = [];
        $uy = [];
        $speed = [];
        $solidMask = [];

        for ($x = 0; $x < $nx; $x++) {
            $density[$x] = [];
            $ux[$x] = [];
            $uy[$x] = [];
            $speed[$x] = [];
            $solidMask[$x] = [];

            for ($y = 0; $y < $ny; $y++) {
                $node = $grid->getNode($x, $y);
                $solidMask[$x][$y] = $node->isSolid();

                if ($node->isSolid()) {
                    $density[$x][$y] = 1.0;
                    $ux[$x][$y] = 0.0;
                    $uy[$x][$y] = 0.0;
                    $speed[$x][$y] = 0.0;
                } else {
                    $density[$x][$y] = $node->getDensity();
                    [$vx, $vy] = $node->getVelocity();
                    $ux[$x][$y] = $vx;
                    $uy[$x][$y] = $vy;
                    $speed[$x][$y] = sqrt($vx * $vx + $vy * $vy);
                }
            }
        }

        // Calculate curl / vorticity: omega = d(uy)/dx - d(ux)/dy
        $vorticity = [];
        for ($x = 0; $x < $nx; $x++) {
            $vorticity[$x] = [];
            for ($y = 0; $y < $ny; $y++) {
                if ($solidMask[$x][$y]) {
                    $vorticity[$x][$y] = 0.0;
                    continue;
                }

                $xPlus  = min($nx - 1, $x + 1);
                $xMinus = max(0, $x - 1);
                $yPlus  = min($ny - 1, $y + 1);
                $yMinus = max(0, $y - 1);

                $duy_dx = ($uy[$xPlus][$y] - $uy[$xMinus][$y]) / (max(1, $xPlus - $xMinus));
                $dux_dy = ($ux[$x][$yPlus] - $ux[$x][$yMinus]) / (max(1, $yPlus - $yMinus));

                $vorticity[$x][$y] = $duy_dx - $dux_dy;
            }
        }

        return new self($nx, $ny, $density, $ux, $uy, $speed, $vorticity, $solidMask);
    }

    public function getNx(): int
    {
        return $this->nx;
    }

    public function getNy(): int
    {
        return $this->ny;
    }

    public function getDensity(int $x, int $y): float
    {
        return $this->density[$x][$y] ?? 1.0;
    }

    public function getVelocity(int $x, int $y): array
    {
        return [$this->ux[$x][$y] ?? 0.0, $this->uy[$x][$y] ?? 0.0];
    }

    public function getSpeed(int $x, int $y): float
    {
        return $this->speed[$x][$y] ?? 0.0;
    }

    public function getVorticity(int $x, int $y): float
    {
        return $this->vorticity[$x][$y] ?? 0.0;
    }

    public function isSolid(int $x, int $y): bool
    {
        return $this->solidMask[$x][$y] ?? false;
    }

    public function getMaxSpeed(): float
    {
        $max = 0.0;
        for ($x = 0; $x < $this->nx; $x++) {
            for ($y = 0; $y < $this->ny; $y++) {
                if (!$this->solidMask[$x][$y] && $this->speed[$x][$y] > $max) {
                    $max = $this->speed[$x][$y];
                }
            }
        }
        return $max;
    }

    public function getAvgDensity(): float
    {
        $sum = 0.0;
        $count = 0;
        for ($x = 0; $x < $this->nx; $x++) {
            for ($y = 0; $y < $this->ny; $y++) {
                if (!$this->solidMask[$x][$y]) {
                    $sum += $this->density[$x][$y];
                    $count++;
                }
            }
        }
        return $count > 0 ? ($sum / $count) : 1.0;
    }

    /**
     * Compute Reynolds number: Re = (U_char * L_char) / nu.
     */
    public function getReynoldsNumber(float $charLength, float $viscosity): float
    {
        if ($viscosity <= 0.0) {
            return 0.0;
        }
        $uChar = $this->getMaxSpeed();
        return ($uChar * $charLength) / $viscosity;
    }

    /**
     * Render ASCII heatmap of velocity magnitude.
     */
    public function renderAscii(int $width = 40, int $height = 12): string
    {
        $chars = [' ', '.', ':', '-', '=', '+', '*', '#', '%', '@'];
        $maxSpeed = max(1e-5, $this->getMaxSpeed());
        $out = "";

        for ($j = $height - 1; $j >= 0; $j--) {
            for ($i = 0; $i < $width; $i++) {
                $gx = (int) round(($i / max(1, $width - 1)) * ($this->nx - 1));
                $gy = (int) round(($j / max(1, $height - 1)) * ($this->ny - 1));

                if ($this->solidMask[$gx][$gy]) {
                    $out .= "█";
                } else {
                    $norm = min(1.0, max(0.0, $this->speed[$gx][$gy] / $maxSpeed));
                    $idx = (int) floor($norm * (count($chars) - 1));
                    $out .= $chars[$idx];
                }
            }
            $out .= PHP_EOL;
        }

        return $out;
    }
}
