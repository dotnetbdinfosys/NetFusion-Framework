<?php
/**
 * NetFusion Framework - Enterprise Plasma Physics & Fusion Reactor Fabric
 *
 * Tokamak toroidal magnetic field geometry and safety factor (q-profile) calculator.
 *
 * @package    NetFusion\Core\Physics\Plasma\Tokamak
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Plasma\Tokamak;

class ToroidalField
{
    /**
     * Vacuum magnetic permeability mu_0 in H/m (T * m / A).
     */
    public const MU_0 = 1.25663706212e-6;

    public function __construct(
        protected readonly float $majorRadius = 6.2,   // R_0 in meters (default ITER benchmark)
        protected readonly float $minorRadius = 2.0,   // a in meters
        protected readonly float $toroidalB0  = 5.3,   // B_0 on-axis in Tesla
        protected readonly float $plasmaCurrent = 15e6,// I_p in Amperes (15 MA)
        protected readonly float $elongation  = 1.7,   // kappa
        protected readonly float $triangularity = 0.33 // delta
    ) {
    }

    public function getMajorRadius(): float
    {
        return $this->majorRadius;
    }

    public function getMinorRadius(): float
    {
        return $this->minorRadius;
    }

    public function getToroidalB0(): float
    {
        return $this->toroidalB0;
    }

    public function getPlasmaCurrent(): float
    {
        return $this->plasmaCurrent;
    }

    public function getElongation(): float
    {
        return $this->elongation;
    }

    public function getTriangularity(): float
    {
        return $this->triangularity;
    }

    /**
     * Aspect ratio: A = R_0 / a
     */
    public function getAspectRatio(): float
    {
        return $this->majorRadius / $this->minorRadius;
    }

    /**
     * Inverse aspect ratio: epsilon = a / R_0
     */
    public function getInverseAspectRatio(): float
    {
        return $this->minorRadius / $this->majorRadius;
    }

    /**
     * Plasma cross-sectional area: S = pi * a^2 * kappa
     */
    public function getCrossSectionArea(): float
    {
        return M_PI * pow($this->minorRadius, 2) * $this->elongation;
    }

    /**
     * Total toroidal plasma volume: V = 2 * pi^2 * R_0 * a^2 * kappa
     */
    public function getPlasmaVolume(): float
    {
        return 2.0 * pow(M_PI, 2) * $this->majorRadius * pow($this->minorRadius, 2) * $this->elongation;
    }

    /**
     * Toroidal magnetic field at major radius R: B_phi(R) = B_0 * (R_0 / R)
     */
    public function getToroidalFieldAt(float $rMajor): float
    {
        if ($rMajor <= 0.0) {
            return $this->toroidalB0;
        }
        return $this->toroidalB0 * ($this->majorRadius / $rMajor);
    }

    /**
     * Average edge poloidal magnetic field: B_theta(a) = mu_0 * I_p / (2 * pi * a_eff)
     */
    public function getEdgePoloidalField(): float
    {
        $perimeter = 2.0 * M_PI * $this->minorRadius * sqrt((1.0 + pow($this->elongation, 2)) / 2.0);
        return (self::MU_0 * $this->plasmaCurrent) / $perimeter;
    }

    /**
     * Kink safety factor at plasma edge (q_edge or q_95 approximation):
     * q_edge = (5 * a^2 * B_0 / (R_0 * I_p_MA)) * ((1 + kappa^2) / 2)
     */
    public function getEdgeSafetyFactor(): float
    {
        $ipMA = $this->plasmaCurrent / 1e6;
        $geomFac = (1.0 + pow($this->elongation, 2)) / 2.0;

        return ((5.0 * pow($this->minorRadius, 2) * $this->toroidalB0) / ($this->majorRadius * $ipMA)) * $geomFac;
    }

    /**
     * Safety factor profile q(rho) as function of normalized flux radius rho in [0, 1].
     */
    public function getSafetyFactorAt(float $rho, float $q0 = 1.05): float
    {
        $qEdge = $this->getEdgeSafetyFactor();
        $rhoClamped = max(0.0, min(1.0, $rho));

        // Monotonic parabolic q-profile: q(rho) = q_0 + (q_edge - q_0) * rho^2
        return $q0 + (($qEdge - $q0) * pow($rhoClamped, 2));
    }

    /**
     * Verify Kruskal-Shafranov stability criterion: q_edge > 1.0 (ideally >= 2.0 to avoid external kink).
     */
    public function isKruskalShafranovStable(float $minQ = 1.0): bool
    {
        return $this->getEdgeSafetyFactor() > $minQ;
    }

    public function toArray(): array
    {
        return [
            'major_radius'      => $this->majorRadius,
            'minor_radius'      => $this->minorRadius,
            'aspect_ratio'      => $this->getAspectRatio(),
            'plasma_volume'     => $this->getPlasmaVolume(),
            'toroidal_b0'       => $this->toroidalB0,
            'plasma_current_ma' => $this->plasmaCurrent / 1e6,
            'edge_poloidal_b'   => $this->getEdgePoloidalField(),
            'edge_q'            => $this->getEdgeSafetyFactor(),
            'ks_stable'         => $this->isKruskalShafranovStable(),
        ];
    }
}
