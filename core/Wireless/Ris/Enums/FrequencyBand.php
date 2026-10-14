<?php
/**
 * NetFusion Framework - Enterprise 6G Wireless RIS Engine
 *
 * Frequency band classifications for sub-THz and 6G communications.
 *
 * @package    NetFusion\Wireless\Ris\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Wireless\Ris\Enums;

enum FrequencyBand: string
{
    case Sub6GHz         = 'sub_6ghz';
    case MillimeterWave  = 'mmwave';
    case Terahertz       = 'terahertz';
    case ExtremeTHz      = 'extreme_thz';

    /**
     * Get center carrier frequency in Hertz.
     */
    public function frequencyHz(): float
    {
        return match ($this) {
            self::Sub6GHz        => 3.5e9,     // 3.5 GHz
            self::MillimeterWave => 28.0e9,    // 28 GHz
            self::Terahertz      => 300.0e9,   // 300 GHz (0.3 THz)
            self::ExtremeTHz     => 1.0e12,    // 1.0 THz
        };
    }

    /**
     * Get free space wavelength in meters (lambda = c / f).
     */
    public function wavelengthMeters(): float
    {
        $speedOfLight = 299792458.0; // m/s
        return $speedOfLight / $this->frequencyHz();
    }

    /**
     * Typical atmospheric / molecular absorption coefficient in dB/km at standard sea level.
     */
    public function molecularAbsorptionDbPerKm(): float
    {
        return match ($this) {
            self::Sub6GHz        => 0.01,
            self::MillimeterWave => 0.15,
            self::Terahertz      => 10.5,
            self::ExtremeTHz     => 85.0,
        };
    }
}

