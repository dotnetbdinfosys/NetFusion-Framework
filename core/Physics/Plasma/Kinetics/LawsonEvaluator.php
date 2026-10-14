<?php
/**
 * NetFusion Framework - Enterprise Plasma Physics & Fusion Reactor Fabric
 *
 * Lawson criterion, fusion triple product (n*T*tau_E), and Q-gain factor evaluator.
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

class LawsonEvaluator
{
    /**
     * D-T Ignition threshold for triple product (m^-3 * keV * s).
     */
    public const DT_IGNITION_TRIPLE_PRODUCT = 3.0e21;

    /**
     * D-T Breakeven threshold for triple product (Q = 1).
     */
    public const DT_BREAKEVEN_TRIPLE_PRODUCT = 5.0e20;

    protected FusionCrossSection $crossSection;

    public function __construct(?FusionCrossSection $crossSection = null)
    {
        $this->crossSection = $crossSection ?? new FusionCrossSection();
    }

    /**
     * Compute plasma triple product: n * T * tau_E.
     *
     * @param float $density Plasma density in m^-3 (e.g. 1e20 m^-3)
     * @param float $temperatureKeV Plasma temperature in keV (e.g. 15 keV)
     * @param float $confinementTimeSec Energy confinement time tau_E in seconds (e.g. 3.0 s)
     * @return float Triple product in m^-3 * keV * s
     */
    public function computeTripleProduct(float $density, float $temperatureKeV, float $confinementTimeSec): float
    {
        return $density * $temperatureKeV * $confinementTimeSec;
    }

    /**
     * Evaluate complete fusion power balance, Q-factor, and ignition status.
     *
     * @param float $plasmaVolume Plasma volume in m^3 (e.g. 840 m^3 for ITER)
     * @param float $density Plasma density in m^-3
     * @param float $temperatureKeV Temperature in keV
     * @param float $confinementTimeSec Confinement time tau_E in seconds
     * @param float $externalHeatingPowerMw Auxiliary heating power in Mega-Watts (e.g. 50 MW)
     * @param FuelMixture $fuel Fuel mixture
     * @return array{
     *     triple_product: float,
     *     fusion_power_mw: float,
     *     alpha_heating_mw: float,
     *     loss_power_mw: float,
     *     q_factor: float,
     *     is_breakeven: bool,
     *     is_ignited: bool
     * }
     */
    public function evaluatePowerBalance(
        float $plasmaVolume = 840.0,
        float $density = 1e20,
        float $temperatureKeV = 15.0,
        float $confinementTimeSec = 3.5,
        float $externalHeatingPowerMw = 50.0,
        FuelMixture $fuel = FuelMixture::D_T
    ): array {
        $densities = $this->crossSection->computePowerDensity($density, $temperatureKeV, $fuel);

        $totalFusionPowerW = $densities['total_power_density_w'] * $plasmaVolume;
        $totalAlphaPowerW  = $densities['charged_power_density_w'] * $plasmaVolume;

        // Plasma thermal energy: W_th = 3/2 * (n_e + n_i) * k_B * T * V = 3 * n * T_J * V
        $tempJ = $temperatureKeV * 1e3 * 1.602176634e-19;
        $thermalEnergyJ = 3.0 * $density * $tempJ * $plasmaVolume;

        // Conduction loss power: P_loss = W_th / tau_E + P_bremsstrahlung
        $conductionLossW = ($confinementTimeSec > 0.0) ? ($thermalEnergyJ / $confinementTimeSec) : 0.0;
        $bremsLossW = $densities['bremsstrahlung_loss_w'] * $plasmaVolume;
        $totalLossPowerW = $conductionLossW + $bremsLossW;

        $pExtW = max(1.0, $externalHeatingPowerMw * 1e6);
        $qFactor = $totalFusionPowerW / $pExtW;

        $tripleProd = $this->computeTripleProduct($density, $temperatureKeV, $confinementTimeSec);

        // Self-sustained ignition: Alpha heating power exceeds all thermal & radiation losses (P_alpha >= P_loss)
        $isIgnited = ($totalAlphaPowerW >= $totalLossPowerW);
        $isBreakeven = ($qFactor >= 1.0);

        return [
            'triple_product'   => $tripleProd,
            'fusion_power_mw'  => $totalFusionPowerW / 1e6,
            'alpha_heating_mw' => $totalAlphaPowerW / 1e6,
            'loss_power_mw'    => $totalLossPowerW / 1e6,
            'q_factor'         => round($qFactor, 2),
            'is_breakeven'     => $isBreakeven,
            'is_ignited'       => $isIgnited,
        ];
    }
}
