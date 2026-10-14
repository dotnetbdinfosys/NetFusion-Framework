<?php
/**
 * NetFusion Framework - Enterprise Numerical Relativity Engine
 *
 * Binary compact object system parameters and post-Newtonian invariants.
 *
 * @package    NetFusion\Core\Relativity\Waveform
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Relativity\Waveform;

use NetFusion\Core\Relativity\Exceptions\RelativityComputationException;

class BinaryChirpSystem
{
    public const SOLAR_MASS_KG = 1.98847e30;
    public const GRAVITATIONAL_CONSTANT = 6.67430e-11;
    public const SPEED_OF_LIGHT = 299792458.0;
    public const MEGA_PARSEC_METERS = 3.085677581491367e22; // 1 Mpc in meters

    private float $totalMassSolar;
    private float $chirpMassSolar;
    private float $symmetricMassRatio; // eta = m1*m2 / (m1+m2)^2

    public function __construct(
        private readonly float $m1Solar,
        private readonly float $m2Solar,
        private readonly float $distanceMpc = 410.0, // e.g. GW150914 distance ~410 Mpc
        private readonly float $inclinationRad = 0.0 // 0 = face-on
    ) {
        if ($this->m1Solar <= 0.0 || $this->m2Solar <= 0.0) {
            throw new RelativityComputationException('Component masses must be strictly positive.');
        }

        if ($this->distanceMpc <= 0.0) {
            throw new RelativityComputationException('Luminosity distance must be strictly positive.');
        }

        $this->totalMassSolar = $this->m1Solar + $this->m2Solar;
        $this->symmetricMassRatio = ($this->m1Solar * $this->m2Solar) / ($this->totalMassSolar * $this->totalMassSolar);

        // Chirp mass M_chirp = (m1 * m2)^(3/5) / (m1 + m2)^(1/5)
        $num = pow($this->m1Solar * $this->m2Solar, 3.0 / 5.0);
        $den = pow($this->totalMassSolar, 1.0 / 5.0);
        $this->chirpMassSolar = $num / $den;
    }

    public function getM1(): float
    {
        return $this->m1Solar;
    }

    public function getM2(): float
    {
        return $this->m2Solar;
    }

    public function getTotalMass(): float
    {
        return $this->totalMassSolar;
    }

    public function getChirpMass(): float
    {
        return $this->chirpMassSolar;
    }

    public function getSymmetricMassRatio(): float
    {
        return $this->symmetricMassRatio;
    }

    public function getDistanceMpc(): float
    {
        return $this->distanceMpc;
    }

    public function getDistanceMeters(): float
    {
        return $this->distanceMpc * self::MEGA_PARSEC_METERS;
    }

    public function getInclination(): float
    {
        return $this->inclinationRad;
    }

    /**
     * Innermost Stable Circular Orbit (ISCO) frequency in Hertz:
     * f_ISCO = c^3 / (6^(3/2) * pi * G * M_total)
     */
    public function getIscoFrequencyHz(): float
    {
        $totalMassKg = $this->totalMassSolar * self::SOLAR_MASS_KG;
        $c3 = pow(self::SPEED_OF_LIGHT, 3);
        $factor = pow(6.0, 1.5) * M_PI * self::GRAVITATIONAL_CONSTANT * $totalMassKg;
        return $c3 / $factor;
    }

    /**
     * Remnant black hole mass estimate in solar masses (accounting for radiative loss ~ 5% of M_tot).
     */
    public function getRemnantMassSolar(): float
    {
        // Fit formula: M_rem = M_tot * (1 - 0.057 * 4 * eta)
        return $this->totalMassSolar * (1.0 - 0.057 * 4.0 * $this->symmetricMassRatio);
    }

    /**
     * Fundamental quasinormal mode ringdown frequency f_QNM ~ 1.2 kHz * (10 M_sun / M_rem).
     */
    public function getRingdownFrequencyHz(): float
    {
        $mRem = $this->getRemnantMassSolar();
        return 1207.0 * (10.0 / $mRem);
    }

    /**
     * Ringdown damping time tau_QNM in seconds.
     */
    public function getRingdownDampingTimeSec(): float
    {
        $mRem = $this->getRemnantMassSolar();
        // tau ~ 0.55 ms * (M_rem / 10 M_sun)
        return 0.00055 * ($mRem / 10.0);
    }
}
