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

enum LaserWavelength: string
{
    case NIR_850NM      = '850nm';
    case O_BAND_1310NM  = '1310nm';
    case C_BAND_1550NM  = '1550nm';
    case MID_IR_2000NM  = '2000nm';

    public function wavelengthNm(): float
    {
        return match ($this) {
            self::NIR_850NM     => 850.0,
            self::O_BAND_1310NM => 1310.0,
            self::C_BAND_1550NM => 1550.0,
            self::MID_IR_2000NM => 2000.0,
        };
    }

    /**
     * Optical carrier frequency in Terahertz (THz) = c / lambda
     */
    public function frequencyThz(): float
    {
        // c ~ 299,792,458 m/s
        return round(299792.458 / $this->wavelengthNm(), 2);
    }

    /**
     * Photon energy in electron-volts (eV) = h * c / lambda
     */
    public function photonEnergyEv(): float
    {
        // 1239.84193 eV*nm
        return round(1239.84193 / $this->wavelengthNm(), 4);
    }

    public function description(): string
    {
        return match ($this) {
            self::NIR_850NM     => 'Short-reach Datacenter Optical Interconnect (VCSEL / GaAs)',
            self::O_BAND_1310NM => 'Zero-Dispersion Optical Telecommunications Band',
            self::C_BAND_1550NM => 'Minimum-Attenuation Silicon Photonics Telecom Band (1530-1565 nm)',
            self::MID_IR_2000NM => 'Mid-Infrared Nonlinear Integrated Photonics Band',
        };
    }
}
