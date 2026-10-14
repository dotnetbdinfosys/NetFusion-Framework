<?php
/**
 * NetFusion Framework - Enterprise Superconductivity & Quantum Magnetics Fabric
 *
 * Ginzburg-Landau macroscopic quantum field theory and temperature scaling engine.
 *
 * @package    NetFusion\Core\Physics\Superconductivity\GinzburgLandau
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Superconductivity\GinzburgLandau;

use NetFusion\Core\Physics\Superconductivity\Enums\SuperconductorMaterial;
use NetFusion\Core\Physics\Superconductivity\Enums\SuperconductorType;

class GinzburgLandauEngine
{
    /**
     * Magnetic flux quantum Phi_0 = h / (2 * e) in Weber (Tesla * m^2).
     */
    public const FLUX_QUANTUM = 2.067833848e-15;

    /**
     * Vacuum permeability mu_0 in H / m.
     */
    public const MU_0 = 1.25663706212e-6;

    /**
     * Compute London penetration depth at temperature T: lambda(T) = lambda_0 / sqrt(1 - (T/Tc)^4).
     */
    public function getPenetrationDepth(SuperconductorMaterial $mat, float $tempK): float
    {
        $tc = $mat->criticalTemperature();
        if ($tempK >= $tc) {
            return INF;
        }

        $t = max(0.0, $tempK / $tc);
        $denom = max(1e-9, 1.0 - pow($t, 4));

        return $mat->penetrationDepth0() / sqrt($denom);
    }

    /**
     * Compute Ginzburg-Landau coherence length at temperature T: xi(T) = xi_0 / sqrt(1 - T/Tc).
     */
    public function getCoherenceLength(SuperconductorMaterial $mat, float $tempK): float
    {
        $tc = $mat->criticalTemperature();
        if ($tempK >= $tc) {
            return 0.0;
        }

        $t = max(0.0, $tempK / $tc);
        $denom = max(1e-9, 1.0 - $t);

        return $mat->coherenceLength0() / sqrt($denom);
    }

    /**
     * Dimensionless Ginzburg-Landau parameter kappa(T) = lambda(T) / xi(T).
     */
    public function getGinzburgLandauKappa(SuperconductorMaterial $mat, float $tempK = 0.0): float
    {
        $tc = $mat->criticalTemperature();
        if ($tempK >= $tc) {
            return $mat->ginzburgLandauKappa();
        }

        $lambda = $this->getPenetrationDepth($mat, $tempK);
        $xi     = $this->getCoherenceLength($mat, $tempK);

        return ($xi > 0.0 && is_finite($lambda)) ? ($lambda / $xi) : $mat->ginzburgLandauKappa();
    }

    /**
     * Upper critical magnetic field B_c2(T) = B_c2(0) * (1 - (T/Tc)^2) in Tesla.
     */
    public function getUpperCriticalField(SuperconductorMaterial $mat, float $tempK): float
    {
        $tc = $mat->criticalTemperature();
        if ($tempK >= $tc) {
            return 0.0;
        }

        $t = max(0.0, $tempK / $tc);
        return $mat->upperCriticalField0() * (1.0 - pow($t, 2));
    }

    /**
     * Lower critical magnetic field B_c1(T) in Tesla (for Type-II).
     * B_c1 = (Phi_0 / (4 * pi * lambda^2)) * ln(kappa).
     */
    public function getLowerCriticalField(SuperconductorMaterial $mat, float $tempK): float
    {
        if ($mat->type() === SuperconductorType::TYPE_I) {
            return $this->getUpperCriticalField($mat, $tempK);
        }

        $tc = $mat->criticalTemperature();
        if ($tempK >= $tc) {
            return 0.0;
        }

        $lambda = $this->getPenetrationDepth($mat, $tempK);
        $kappa  = $this->getGinzburgLandauKappa($mat, $tempK);

        if (!is_finite($lambda) || $lambda <= 0.0 || $kappa <= 1.0) {
            return 0.0;
        }

        return (self::FLUX_QUANTUM / (4.0 * M_PI * pow($lambda, 2))) * log($kappa + 0.08);
    }

    /**
     * Theoretical Ginzburg-Landau depairing critical current density J_c(T) in A / m^2:
     * J_c = Phi_0 / (3 * sqrt(3) * pi * mu_0 * lambda^2 * xi).
     */
    public function getDepairingCurrentDensity(SuperconductorMaterial $mat, float $tempK): float
    {
        $tc = $mat->criticalTemperature();
        if ($tempK >= $tc) {
            return 0.0;
        }

        $lambda = $this->getPenetrationDepth($mat, $tempK);
        $xi     = $this->getCoherenceLength($mat, $tempK);

        if (!is_finite($lambda) || $xi <= 0.0) {
            return 0.0;
        }

        $denom = 3.0 * sqrt(3.0) * M_PI * self::MU_0 * pow($lambda, 2) * $xi;

        return self::FLUX_QUANTUM / $denom;
    }

    /**
     * Condensation free energy density difference Delta f = B_c^2 / (2 * mu_0) in Joules / m^3.
     */
    public function getCondensationEnergyDensity(SuperconductorMaterial $mat, float $tempK): float
    {
        $bc = $this->getUpperCriticalField($mat, $tempK);
        if ($mat->type() === SuperconductorType::TYPE_II) {
            // Thermodynamic critical field Bc ~ Bc2 / (sqrt(2) * kappa)
            $kappa = $mat->ginzburgLandauKappa();
            $bc = $bc / (sqrt(2.0) * $kappa);
        }

        return pow($bc, 2) / (2.0 * self::MU_0);
    }
}
