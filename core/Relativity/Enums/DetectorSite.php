<?php
/**
 * NetFusion Framework - Enterprise Numerical Relativity Engine
 *
 * Gravitational wave terrestrial and space-based detector observatories.
 *
 * @package    NetFusion\Core\Relativity\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Relativity\Enums;

enum DetectorSite: string
{
    case LigoHanford    = 'H1';   // LIGO Hanford Observatory, Washington (USA)
    case LigoLivingston = 'L1';   // LIGO Livingston Observatory, Louisiana (USA)
    case Virgo          = 'V1';   // Virgo Interferometer, Cascina (Italy)
    case Lisa           = 'LISA'; // Laser Interferometer Space Antenna (ESA/NASA)

    /**
     * Detector baseline arm length in kilometers.
     */
    public function armLengthKm(): float
    {
        return match ($this) {
            self::LigoHanford, self::LigoLivingston => 4.0,
            self::Virgo                             => 3.0,
            self::Lisa                              => 2.5e6, // 2.5 million km
        };
    }

    /**
     * Approximate optimal detection bandwidth [f_min, f_max] in Hertz.
     *
     * @return array{minHz: float, maxHz: float}
     */
    public function bandwidth(): array
    {
        return match ($this) {
            self::LigoHanford, self::LigoLivingston => ['minHz' => 10.0, 'maxHz' => 5000.0],
            self::Virgo                             => ['minHz' => 15.0, 'maxHz' => 4500.0],
            self::Lisa                              => ['minHz' => 1e-4, 'maxHz' => 0.1],
        };
    }
}
