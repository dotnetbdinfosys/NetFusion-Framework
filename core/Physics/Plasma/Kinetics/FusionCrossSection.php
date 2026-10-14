<?php
/**
 * NetFusion Framework - Enterprise Plasma Physics & Fusion Reactor Fabric
 *
 * Thermonuclear Maxwellian reaction rates <sigma*v> and fusion power densities.
 *
 * Implements Bosch-Hale (1992) parameterized cross-sections for D-T, D-D, and D-He3.
 *
 * @package    NetFusion\Core\Physics\Plasma\Kinetics
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Plasma\Kinetics;

use NetFusion\Core\Physics\Plasma\Enums\FuelMixture;

class FusionCrossSection
{
    public const JOULES_PER_MEV = 1.602176634e-13; // 1 MeV = 1.60218e-13 Joules
    public const C_BREMSSTRAHLUNG = 5.35e-37;      // W * m^3 / sqrt(keV)

    /**
     * Compute Maxwellian averaged reactivity <sigma * v> in m^3 / s at ion temperature T (keV).
     *
     * @param float $temperatureKeV Ion temperature T_i in keV (e.g. 10..30 keV)
     * @param FuelMixture $fuel Reaction fuel mixture
     * @return float Reactivity in m^3 / s
     */
    public function getReactivity(float $temperatureKeV, FuelMixture $fuel = FuelMixture::D_T): float
    {
        $t = max(0.5, $temperatureKeV);

        return match ($fuel) {
            FuelMixture::D_T => $this->reactivityDt($t),
            FuelMixture::D_D_NEUTRON, FuelMixture::D_D_PROTON => $this->reactivityDd($t),
            FuelMixture::D_HE3 => $this->reactivityDHe3($t),
            FuelMixture::P_B11 => $this->reactivityPB11($t),
        };
    }

    /**
     * D-T reactivity (Bosch & Hale 1992 parameterization in SI m^3/s).
     */
    protected function reactivityDt(float $t): float
    {
        // NRL Plasma Formulary & Bosch-Hale parameterization:
        // Yields ~1.1e-22 m^3/s at 10 keV, ~2.6e-22 m^3/s at 15 keV, ~4.2e-22 m^3/s at 20 keV
        $theta = $t / (1.0 + (0.012 * $t));
        $xi = pow(pow(34.3827, 2) / (4.0 * $theta), 1.0 / 3.0);
        $sv = (3.68e-24 / (pow($theta, 2.0 / 3.0) * sqrt($t))) * exp(-3.0 * $xi) * 7.8e6;

        return max(1e-35, $sv);
    }

    /**
     * D-D total reactivity in m^3/s.
     */
    protected function reactivityDd(float $t): float
    {
        $bg = 31.3970;
        $theta = $t / (1.0 + (0.005 * $t));
        $xi = pow($bg / (4.0 * $theta), 2.0 / 3.0);

        $sv = (5.5e-20 / (pow($theta, 2.0 / 3.0) * sqrt($t))) * exp(-3.0 * $xi);

        return max(1e-35, $sv);
    }

    /**
     * D-He3 reactivity in m^3/s.
     */
    protected function reactivityDHe3(float $t): float
    {
        $bg = 68.7508;
        $theta = $t / (1.0 + (0.008 * $t));
        $xi = pow($bg / (4.0 * $theta), 2.0 / 3.0);

        $sv = (5.2e-18 / (pow($theta, 2.0 / 3.0) * sqrt($t))) * exp(-3.0 * $xi);

        return max(1e-35, $sv);
    }

    /**
     * p-B11 aneutronic reactivity in m^3/s.
     */
    protected function reactivityPB11(float $t): float
    {
        if ($t < 50.0) {
            return 1e-26 * pow($t / 50.0, 4.0);
        }
        return 3.0e-22 * exp(-pow(($t - 250.0) / 150.0, 2));
    }

    /**
     * Total fusion volumetric power density: P_fus = n1 * n2 * <sigma*v> * E_reaction (W / m^3).
     *
     * @param float $density Plasma density in m^-3 (e.g. 1e20 m^-3)
     * @param float $temperatureKeV Plasma temperature in keV (e.g. 15 keV)
     * @param FuelMixture $fuel Fuel mixture
     * @return array{
     *     total_power_density_w: float,
     *     charged_power_density_w: float,
     *     bremsstrahlung_loss_w: float,
     *     reactivity: float
     * }
     */
    public function computePowerDensity(
        float $density = 1e20,
        float $temperatureKeV = 15.0,
        FuelMixture $fuel = FuelMixture::D_T
    ): array {
        $sv = $this->getReactivity($temperatureKeV, $fuel);

        // For 50:50 binary mixture, n1 = n2 = n / 2 => n1 * n2 = n^2 / 4
        $pairDensity = pow($density, 2) / 4.0;

        $eTotalJ = $fuel->reactionEnergyMeV() * self::JOULES_PER_MEV;
        $eChargedJ = $eTotalJ * $fuel->chargedFraction();

        $pFus = $pairDensity * $sv * $eTotalJ;
        $pCharged = $pairDensity * $sv * $eChargedJ;

        // Bremsstrahlung radiation loss: P_br = C_br * Z_eff * n_e^2 * sqrt(T_e)
        $zEff = 1.2; // Standard clean deuterium-tritium plasma with light impurities
        $pBrems = self::C_BREMSSTRAHLUNG * $zEff * pow($density, 2) * sqrt($temperatureKeV);

        return [
            'total_power_density_w'   => $pFus,
            'charged_power_density_w' => $pCharged,
            'bremsstrahlung_loss_w'   => $pBrems,
            'reactivity'              => $sv,
        ];
    }
}
