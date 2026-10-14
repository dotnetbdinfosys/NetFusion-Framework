<?php
/**
 * NetFusion Framework - Enterprise Astro-Informatics & Celestial Mechanics Fabric
 *
 * Classical Keplerian orbital element representations.
 *
 * @package    NetFusion\Core\Astro\Orbit
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Astro\Orbit;

class KeplerianElements
{
    /**
     * Standard gravitational parameter of the Sun in m^3/s^2.
     */
    public const MU_SUN = 1.32712440018e20;

    /**
     * Standard gravitational parameter of the Earth in m^3/s^2.
     */
    public const MU_EARTH = 3.986004418e14;

    /**
     * Astronomical Unit (AU) in meters.
     */
    public const AU_METERS = 1.495978707e11;

    public function __construct(
        protected readonly float $semiMajorAxis,                 // a (meters or AU depending on context)
        protected readonly float $eccentricity,                   // e (dimensionless, 0 <= e < 1 for bound orbit)
        protected readonly float $inclination = 0.0,             // i (radians)
        protected readonly float $longitudeOfAscendingNode = 0.0,// Omega (radians)
        protected readonly float $argumentOfPeriapsis = 0.0,      // omega (radians)
        protected readonly float $trueAnomaly = 0.0,             // nu (radians)
        protected readonly float $mu = self::MU_SUN              // Standard gravitational parameter (m^3/s^2)
    ) {
    }

    public function getSemiMajorAxis(): float
    {
        return $this->semiMajorAxis;
    }

    public function getEccentricity(): float
    {
        return $this->eccentricity;
    }

    public function getInclination(): float
    {
        return $this->inclination;
    }

    public function getLongitudeOfAscendingNode(): float
    {
        return $this->longitudeOfAscendingNode;
    }

    public function getArgumentOfPeriapsis(): float
    {
        return $this->argumentOfPeriapsis;
    }

    public function getTrueAnomaly(): float
    {
        return $this->trueAnomaly;
    }

    public function getMu(): float
    {
        return $this->mu;
    }

    /**
     * Periapsis distance: r_p = a * (1 - e)
     */
    public function getPeriapsis(): float
    {
        return $this->semiMajorAxis * (1.0 - $this->eccentricity);
    }

    /**
     * Apoapsis distance: r_a = a * (1 + e)
     */
    public function getApoapsis(): float
    {
        return $this->semiMajorAxis * (1.0 + $this->eccentricity);
    }

    /**
     * Orbital period in seconds: T = 2 * pi * sqrt(a^3 / mu)
     */
    public function getPeriod(): float
    {
        if ($this->semiMajorAxis <= 0.0 || $this->mu <= 0.0) {
            return 0.0;
        }
        return 2.0 * M_PI * sqrt(pow($this->semiMajorAxis, 3) / $this->mu);
    }

    /**
     * Specific orbital energy: epsilon = -mu / (2 * a)
     */
    public function getSpecificEnergy(): float
    {
        if ($this->semiMajorAxis <= 0.0) {
            return 0.0;
        }
        return -$this->mu / (2.0 * $this->semiMajorAxis);
    }

    /**
     * Specific relative angular momentum: h = sqrt(mu * a * (1 - e^2))
     */
    public function getSpecificAngularMomentum(): float
    {
        $p = $this->semiMajorAxis * (1.0 - pow($this->eccentricity, 2));
        return sqrt(max(0.0, $this->mu * $p));
    }

    public function toArray(): array
    {
        return [
            'semi_major_axis'   => $this->semiMajorAxis,
            'eccentricity'      => $this->eccentricity,
            'inclination'       => $this->inclination,
            'raan'              => $this->longitudeOfAscendingNode,
            'arg_periapsis'     => $this->argumentOfPeriapsis,
            'true_anomaly'      => $this->trueAnomaly,
            'periapsis'         => $this->getPeriapsis(),
            'apoapsis'          => $this->getApoapsis(),
            'period_seconds'    => $this->getPeriod(),
            'specific_energy'   => $this->getSpecificEnergy(),
        ];
    }
}
