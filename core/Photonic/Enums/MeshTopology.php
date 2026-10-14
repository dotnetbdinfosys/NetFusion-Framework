<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Optical Photonic Computing & Tensor Light-Interferometer
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Photonic\Enums;

enum MeshTopology: string
{
    case CLEMENTS = 'clements'; // Rectangular grid with optimal optical depth N
    case RECK     = 'reck';     // Triangular layout with progressive beam splitter pairs
    case DIAMOND  = 'diamond';  // Hexagonal diamond planar configuration

    public function opticalDepth(int $numPorts): int
    {
        return match ($this) {
            self::CLEMENTS => $numPorts,
            self::RECK     => 2 * $numPorts - 3,
            self::DIAMOND  => $numPorts + 1,
        };
    }
}
