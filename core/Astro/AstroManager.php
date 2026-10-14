<?php
/**
 * NetFusion Framework - Enterprise Astro-Informatics & Celestial Mechanics Fabric
 *
 * Master orchestrator facade managing N-body gravitational systems,
 * orbital ephemeris, Hohmann transfer maneuvers, and Lagrange points.
 *
 * @package    NetFusion\Core\Astro
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Astro;

use NetFusion\Core\Astro\Body\CelestialBody;
use NetFusion\Core\Astro\Enums\CelestialBodyType;
use NetFusion\Core\Astro\Enums\IntegratorType;
use NetFusion\Core\Astro\Integrator\SymplecticNBodyIntegrator;
use NetFusion\Core\Astro\Orbit\KeplerianElements;
use NetFusion\Core\Astro\Orbit\OrbitalMechanics;

class AstroManager
{
    /**
     * @var array<string, CelestialBody>
     */
    protected array $bodies = [];

    protected OrbitalMechanics $orbitalMechanics;
    protected SymplecticNBodyIntegrator $integrator;

    public function __construct(
        ?OrbitalMechanics $orbitalMechanics = null,
        ?SymplecticNBodyIntegrator $integrator = null
    ) {
        $this->orbitalMechanics = $orbitalMechanics ?? new OrbitalMechanics();
        $this->integrator       = $integrator ?? new SymplecticNBodyIntegrator();
    }

    public function addBody(CelestialBody $body): void
    {
        $this->bodies[$body->getBodyId()] = $body;
    }

    public function getBody(string $bodyId): ?CelestialBody
    {
        return $this->bodies[$bodyId] ?? null;
    }

    /**
     * @return array<string, CelestialBody>
     */
    public function getBodies(): array
    {
        return $this->bodies;
    }

    public function removeBody(string $bodyId): void
    {
        unset($this->bodies[$bodyId]);
    }

    public function clearBodies(): void
    {
        $this->bodies = [];
    }

    /**
     * Load canonical Sun-Earth-Moon mini gravitational system for simulations.
     */
    public function loadMiniSolarSystem(): void
    {
        $this->clearBodies();

        // 1. Sun (at origin)
        $sun = new CelestialBody(
            'sun',
            'Sun',
            CelestialBodyType::STAR,
            1.989e30,
            6.9634e8,
            ['x' => 0.0, 'y' => 0.0, 'z' => 0.0],
            ['x' => 0.0, 'y' => 0.0, 'z' => 0.0]
        );

        // 2. Earth (1 AU from Sun, speed ~ 29.78 km/s)
        $earthR = 1.496e11;
        $earthV = 29780.0;
        $earth = new CelestialBody(
            'earth',
            'Earth',
            CelestialBodyType::PLANET,
            5.972e24,
            6.371e6,
            ['x' => $earthR, 'y' => 0.0, 'z' => 0.0],
            ['x' => 0.0, 'y' => $earthV, 'z' => 0.0]
        );

        // 3. Moon (orbiting Earth at 384,400 km, orbital speed relative to Earth ~ 1022 m/s)
        $moonDist = 3.844e8;
        $moonV = 1022.0;
        $moon = new CelestialBody(
            'moon',
            'Moon',
            CelestialBodyType::MOON,
            7.342e22,
            1.737e6,
            ['x' => $earthR + $moonDist, 'y' => 0.0, 'z' => 0.0],
            ['x' => 0.0, 'y' => $earthV + $moonV, 'z' => 0.0]
        );

        $this->addBody($sun);
        $this->addBody($earth);
        $this->addBody($moon);
    }

    /**
     * Propagate the N-body gravitational system across multiple timesteps.
     *
     * @return array<int, array{step: int, energy: array{kinetic: float, potential: float, total: float}, bodies: array<string, array>}>
     */
    public function propagate(int $steps = 100, float $dt = 86400.0, IntegratorType $type = IntegratorType::LEAPFROG): array
    {
        $history = [];
        $bodyList = array_values($this->bodies);

        for ($step = 0; $step <= $steps; $step++) {
            $energy = $this->integrator->computeMechanicalEnergy($bodyList);

            $snapshot = [];
            foreach ($this->bodies as $id => $b) {
                $snapshot[$id] = [
                    'position' => $b->getPosition(),
                    'velocity' => $b->getVelocity(),
                    'speed'    => $b->speed(),
                ];
            }

            $history[] = [
                'step'   => $step,
                'energy' => $energy,
                'bodies' => $snapshot,
            ];

            if ($step < $steps) {
                if ($type === IntegratorType::YOSHIDA_4TH) {
                    $this->integrator->stepYoshida4th($bodyList, $dt);
                } else {
                    $this->integrator->stepLeapfrog($bodyList, $dt);
                }
            }
        }

        return $history;
    }

    /**
     * Solve Keplerian orbit to Cartesian state vectors.
     */
    public function propagateKeplerian(KeplerianElements $elements, float $deltaMeanAnomaly = 0.0): array
    {
        $mNew = $elements->getTrueAnomaly() + $deltaMeanAnomaly;
        $eVal = $this->orbitalMechanics->solveKepler($mNew, $elements->getEccentricity());
        $nu = $this->orbitalMechanics->eccentricToTrueAnomaly($eVal, $elements->getEccentricity());

        $propagatedElements = new KeplerianElements(
            $elements->getSemiMajorAxis(),
            $elements->getEccentricity(),
            $elements->getInclination(),
            $elements->getLongitudeOfAscendingNode(),
            $elements->getArgumentOfPeriapsis(),
            $nu,
            $elements->getMu()
        );

        return $this->orbitalMechanics->elementsToCartesian($propagatedElements);
    }

    /**
     * Compute Hohmann interplanetary delta-v transfer.
     */
    public function calculateHohmann(float $r1, float $r2, float $mu = KeplerianElements::MU_SUN): array
    {
        return $this->orbitalMechanics->computeHohmannTransfer($r1, $r2, $mu);
    }

    /**
     * Compute Lagrange equilibrium points.
     */
    public function calculateLagrange(float $m1, float $m2, float $distance = 1.0): array
    {
        return $this->orbitalMechanics->computeLagrangePoints($m1, $m2, $distance);
    }

    public function getOrbitalMechanics(): OrbitalMechanics
    {
        return $this->orbitalMechanics;
    }

    public function getIntegrator(): SymplecticNBodyIntegrator
    {
        return $this->integrator;
    }
}
