<?php
/**
 * NetFusion Framework - Enterprise 6G Wireless RIS Engine
 *
 * 3D spatial wave vector and electromagnetic propagation modeling.
 *
 * @package    NetFusion\Wireless\Ris\Physics
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Wireless\Ris\Physics;

use NetFusion\Core\Wireless\Ris\Enums\FrequencyBand;
use NetFusion\Core\Wireless\Ris\Exceptions\TerahertzChannelException;

class WaveVector
{
    public const SPEED_OF_LIGHT = 299792458.0; // m/s

    private float $kx;
    private float $ky;
    private float $kz;
    private float $wavenumber; // k = 2 * pi / lambda

    public function __construct(
        private readonly FrequencyBand $band,
        private readonly float $azimuthRad,   // phi in [0, 2pi)
        private readonly float $elevationRad // theta in [-pi/2, pi/2]
    ) {
        if ($this->elevationRad < -M_PI_2 || $this->elevationRad > M_PI_2) {
            throw new TerahertzChannelException('Elevation angle must be between -pi/2 and pi/2 radians.');
        }

        $wavelength = $this->band->wavelengthMeters();
        $this->wavenumber = 2.0 * M_PI / $wavelength;

        // Spherical to Cartesian wavevector components
        // kx = k * cos(elevation) * cos(azimuth)
        // ky = k * cos(elevation) * sin(azimuth)
        // kz = k * sin(elevation)
        $cosEl = cos($this->elevationRad);
        $this->kx = $this->wavenumber * $cosEl * cos($this->azimuthRad);
        $this->ky = $this->wavenumber * $cosEl * sin($this->azimuthRad);
        $this->kz = $this->wavenumber * sin($this->elevationRad);
    }

    public function getBand(): FrequencyBand
    {
        return $this->band;
    }

    public function getWavelength(): float
    {
        return $this->band->wavelengthMeters();
    }

    public function getWavenumber(): float
    {
        return $this->wavenumber;
    }

    public function getAzimuth(): float
    {
        return $this->azimuthRad;
    }

    public function getElevation(): float
    {
        return $this->elevationRad;
    }

    public function getKx(): float
    {
        return $this->kx;
    }

    public function getKy(): float
    {
        return $this->ky;
    }

    public function getKz(): float
    {
        return $this->kz;
    }

    /**
     * Calculate free space path loss in linear power ratio (Friis transmission).
     * FSPL = (4 * pi * distance / lambda)^2
     */
    public function calculateFreeSpaceLoss(float $distanceMeters): float
    {
        if ($distanceMeters <= 0.0) {
            throw new TerahertzChannelException('Distance must be strictly positive.');
        }

        $factor = (4.0 * M_PI * $distanceMeters) / $this->getWavelength();
        return $factor * $factor;
    }

    /**
     * Calculate molecular absorption loss factor for Terahertz channels: e^(-k_abs * distance)
     */
    public function calculateMolecularAbsorptionLoss(float $distanceMeters): float
    {
        $dbPerKm = $this->band->molecularAbsorptionDbPerKm();
        $distanceKm = $distanceMeters / 1000.0;
        $totalLossDb = $dbPerKm * $distanceKm;
        
        // Convert dB loss to linear power loss attenuation factor (1.0 = no loss, < 1.0 = attenuated)
        return pow(10.0, -$totalLossDb / 10.0);
    }

    /**
     * Calculate phase shift acquired by a plane wave at planar position (x, y).
     * delta_phi = -(kx * x + ky * y)
     */
    public function phaseAtPlane(float $x, float $y): float
    {
        return -($this->kx * $x + $this->ky * $y);
    }
}

