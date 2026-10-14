<?php
/**
 * NetFusion Framework - Enterprise Plasma Physics & Fusion Reactor Fabric
 *
 * Master orchestrator facade managing Tokamak magnetic geometries,
 * MHD equilibrium, thermonuclear reaction kinetics, and Lawson criteria.
 *
 * @package    NetFusion\Core\Physics\Plasma
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Plasma;

use NetFusion\Core\Physics\Plasma\Enums\FuelMixture;
use NetFusion\Core\Physics\Plasma\Enums\InstabilityMode;
use NetFusion\Core\Physics\Plasma\Exceptions\PlasmaInstabilityException;
use NetFusion\Core\Physics\Plasma\Kinetics\FusionCrossSection;
use NetFusion\Core\Physics\Plasma\Kinetics\LawsonEvaluator;
use NetFusion\Core\Physics\Plasma\Mhd\GradShafranovSolver;
use NetFusion\Core\Physics\Plasma\Tokamak\ToroidalField;

class PlasmaManager
{
    protected ToroidalField $tokamak;
    protected GradShafranovSolver $mhdSolver;
    protected FusionCrossSection $crossSection;
    protected LawsonEvaluator $lawsonEvaluator;

    public function __construct(
        ?ToroidalField $tokamak = null,
        ?GradShafranovSolver $mhdSolver = null,
        ?FusionCrossSection $crossSection = null,
        ?LawsonEvaluator $lawsonEvaluator = null
    ) {
        $this->tokamak         = $tokamak ?? new ToroidalField(); // Default: ITER benchmark
        $this->mhdSolver       = $mhdSolver ?? new GradShafranovSolver();
        $this->crossSection    = $crossSection ?? new FusionCrossSection();
        $this->lawsonEvaluator = $lawsonEvaluator ?? new LawsonEvaluator($this->crossSection);
    }

    public function getTokamak(): ToroidalField
    {
        return $this->tokamak;
    }

    public function setTokamak(ToroidalField $tokamak): void
    {
        $this->tokamak = $tokamak;
    }

    /**
     * Compute safety factor q at plasma radius rho.
     */
    public function getSafetyFactor(float $rho = 1.0): float
    {
        return $this->tokamak->getSafetyFactorAt($rho);
    }

    /**
     * Verify MHD stability guardrails (Kruskal-Shafranov and Troyon beta).
     */
    public function verifyMhdStability(float $betaToroidal = 0.025): array
    {
        $qEdge = $this->tokamak->getEdgeSafetyFactor();
        $ksStable = $this->tokamak->isKruskalShafranovStable(1.0);

        $ipMA = $this->tokamak->getPlasmaCurrent() / 1e6;
        $a = $this->tokamak->getMinorRadius();
        $b0 = $this->tokamak->getToroidalB0();
        $troyonLimit = (2.8 * ($ipMA / ($a * $b0))) / 100.0;
        $troyonStable = ($betaToroidal <= $troyonLimit);

        $modes = [];
        if (!$ksStable) {
            $modes[] = InstabilityMode::KINK;
        }
        if (!$troyonStable) {
            $modes[] = InstabilityMode::BALLOONING;
        }

        return [
            'q_edge'        => round($qEdge, 3),
            'ks_stable'     => $ksStable,
            'beta_t'        => $betaToroidal,
            'troyon_limit'  => round($troyonLimit, 4),
            'troyon_stable' => $troyonStable,
            'active_modes'  => array_map(fn($m) => $m->value, $modes),
        ];
    }

    /**
     * Run 2D Grad-Shafranov equilibrium simulation.
     */
    public function solveEquilibrium(int $nR = 25, int $nZ = 25, float $corePressure = 1.5e5): array
    {
        return $this->mhdSolver->solveEquilibrium($this->tokamak, $nR, $nZ, $corePressure);
    }

    /**
     * Calculate Maxwellian fusion reaction rate and volumetric power density.
     */
    public function calculateReactionRate(float $temperatureKeV = 15.0, FuelMixture $fuel = FuelMixture::D_T): array
    {
        return $this->crossSection->computePowerDensity(1e20, $temperatureKeV, $fuel);
    }

    /**
     * Evaluate Lawson triple product, Q-energy gain, and ignition power balance.
     */
    public function evaluateLawson(
        float $density = 1e20,
        float $temperatureKeV = 15.0,
        float $confinementTimeSec = 3.5,
        float $auxHeatingMw = 50.0,
        FuelMixture $fuel = FuelMixture::D_T
    ): array {
        $volume = $this->tokamak->getPlasmaVolume();

        return $this->lawsonEvaluator->evaluatePowerBalance(
            $volume,
            $density,
            $temperatureKeV,
            $confinementTimeSec,
            $auxHeatingMw,
            $fuel
        );
    }

    public function getMhdSolver(): GradShafranovSolver
    {
        return $this->mhdSolver;
    }

    public function getCrossSection(): FusionCrossSection
    {
        return $this->crossSection;
    }

    public function getLawsonEvaluator(): LawsonEvaluator
    {
        return $this->lawsonEvaluator;
    }
}
