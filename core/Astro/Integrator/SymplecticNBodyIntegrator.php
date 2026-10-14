<?php
/**
 * NetFusion Framework - Enterprise Astro-Informatics & Celestial Mechanics Fabric
 *
 * Symplectic N-Body gravitational dynamical integrator (Leapfrog & 4th-Order Yoshida).
 *
 * Strictly preserves symplectic phase-space volume and total mechanical energy.
 *
 * @package    NetFusion\Core\Astro\Integrator
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Astro\Integrator;

use NetFusion\Core\Astro\Body\CelestialBody;
use NetFusion\Core\Astro\Enums\IntegratorType;

class SymplecticNBodyIntegrator
{
    /**
     * Universal Gravitational Constant G in m^3 / (kg * s^2).
     */
    public const G = 6.67430e-11;

    /**
     * Plummer softening length in meters to prevent numerical singularity on close encounters.
     */
    protected float $softening = 1e3;

    public function __construct(float $softening = 1e3)
    {
        $this->softening = $softening;
    }

    /**
     * Compute instantaneous gravitational accelerations on all N bodies.
     *
     * @param CelestialBody[] $bodies
     * @return array<int, array{x: float, y: float, z: float}>
     */
    public function computeAccelerations(array $bodies): array
    {
        $n = count($bodies);
        $accelerations = [];

        for ($i = 0; $i < $n; $i++) {
            $accelerations[$i] = ['x' => 0.0, 'y' => 0.0, 'z' => 0.0];
        }

        $eps2 = $this->softening * $this->softening;

        for ($i = 0; $i < $n; $i++) {
            $pI = $bodies[$i]->getPosition();

            for ($j = $i + 1; $j < $n; $j++) {
                $pJ = $bodies[$j]->getPosition();
                $mI = $bodies[$i]->getMass();
                $mJ = $bodies[$j]->getMass();

                $dx = $pJ['x'] - $pI['x'];
                $dy = $pJ['y'] - $pI['y'];
                $dz = $pJ['z'] - $pI['z'];

                $r2 = ($dx * $dx) + ($dy * $dy) + ($dz * $dz) + $eps2;
                $invR3 = 1.0 / ($r2 * sqrt($r2));

                $gFac = self::G * $invR3;

                // Force on body i due to j: F_ij = G * M_i * M_j * r_ij / r^3
                $axI = $gFac * $mJ * $dx;
                $ayI = $gFac * $mJ * $dy;
                $azI = $gFac * $mJ * $dz;

                $accelerations[$i]['x'] += $axI;
                $accelerations[$i]['y'] += $ayI;
                $accelerations[$i]['z'] += $azI;

                // Newton's third law: a_j = -a_i * (M_i / M_j)
                $accelerations[$j]['x'] -= $gFac * $mI * $dx;
                $accelerations[$j]['y'] -= $gFac * $mI * $dy;
                $accelerations[$j]['z'] -= $gFac * $mI * $dz;
            }
        }

        return $accelerations;
    }

    /**
     * Advance N-body gravitational system by timestep dt using Leapfrog (Kick-Drift-Kick).
     *
     * @param CelestialBody[] $bodies
     */
    public function stepLeapfrog(array $bodies, float $dt): void
    {
        $n = count($bodies);
        $halfDt = 0.5 * $dt;

        // Kick 1: v(t + dt/2) = v(t) + a(t) * dt/2
        $a0 = $this->computeAccelerations($bodies);
        for ($i = 0; $i < $n; $i++) {
            $v = $bodies[$i]->getVelocity();
            $v['x'] += $a0[$i]['x'] * $halfDt;
            $v['y'] += $a0[$i]['y'] * $halfDt;
            $v['z'] += $a0[$i]['z'] * $halfDt;
            $bodies[$i]->setVelocity($v);
        }

        // Drift: r(t + dt) = r(t) + v(t + dt/2) * dt
        for ($i = 0; $i < $n; $i++) {
            $p = $bodies[$i]->getPosition();
            $v = $bodies[$i]->getVelocity();
            $p['x'] += $v['x'] * $dt;
            $p['y'] += $v['y'] * $dt;
            $p['z'] += $v['z'] * $dt;
            $bodies[$i]->setPosition($p);
        }

        // Kick 2: v(t + dt) = v(t + dt/2) + a(t + dt) * dt/2
        $a1 = $this->computeAccelerations($bodies);
        for ($i = 0; $i < $n; $i++) {
            $v = $bodies[$i]->getVelocity();
            $v['x'] += $a1[$i]['x'] * $halfDt;
            $v['y'] += $a1[$i]['y'] * $halfDt;
            $v['z'] += $a1[$i]['z'] * $halfDt;
            $bodies[$i]->setVelocity($v);
        }
    }

    /**
     * Advance N-body system using 4th-order Yoshida symplectic integrator (Yoshida 1990).
     *
     * @param CelestialBody[] $bodies
     */
    public function stepYoshida4th(array $bodies, float $dt): void
    {
        $cRoot = pow(2.0, 1.0 / 3.0);
        $w0 = -$cRoot / (2.0 - $cRoot);
        $w1 = 1.0 / (2.0 - $cRoot);

        // Substep intervals
        $c1 = $w1 * 0.5;
        $c2 = ($w0 + $w1) * 0.5;
        $c3 = $c2;
        $c4 = $c1;

        $d1 = $w1;
        $d2 = $w0;
        $d3 = $w1;

        $n = count($bodies);

        // Substep 1: Drift c1, Kick d1
        $this->drift($bodies, $c1 * $dt);
        $this->kick($bodies, $d1 * $dt);

        // Substep 2: Drift c2, Kick d2
        $this->drift($bodies, $c2 * $dt);
        $this->kick($bodies, $d2 * $dt);

        // Substep 3: Drift c3, Kick d3
        $this->drift($bodies, $c3 * $dt);
        $this->kick($bodies, $d3 * $dt);

        // Substep 4: Drift c4
        $this->drift($bodies, $c4 * $dt);
    }

    /**
     * Helper to drift positions: r += v * dt
     *
     * @param CelestialBody[] $bodies
     */
    protected function drift(array $bodies, float $dt): void
    {
        foreach ($bodies as $b) {
            $p = $b->getPosition();
            $v = $b->getVelocity();
            $p['x'] += $v['x'] * $dt;
            $p['y'] += $v['y'] * $dt;
            $p['z'] += $v['z'] * $dt;
            $b->setPosition($p);
        }
    }

    /**
     * Helper to kick velocities: v += a * dt
     *
     * @param CelestialBody[] $bodies
     */
    protected function kick(array $bodies, float $dt): void
    {
        $a = $this->computeAccelerations($bodies);
        $n = count($bodies);
        for ($i = 0; $i < $n; $i++) {
            $v = $bodies[$i]->getVelocity();
            $v['x'] += $a[$i]['x'] * $dt;
            $v['y'] += $a[$i]['y'] * $dt;
            $v['z'] += $a[$i]['z'] * $dt;
            $bodies[$i]->setVelocity($v);
        }
    }

    /**
     * Calculate total mechanical energy of the system: E = T (Kinetic) + V (Potential).
     *
     * @param CelestialBody[] $bodies
     * @return array{kinetic: float, potential: float, total: float}
     */
    public function computeMechanicalEnergy(array $bodies): array
    {
        $kinetic = 0.0;
        foreach ($bodies as $b) {
            $kinetic += $b->kineticEnergy();
        }

        $potential = 0.0;
        $n = count($bodies);
        $eps2 = $this->softening * $this->softening;

        for ($i = 0; $i < $n; $i++) {
            $pI = $bodies[$i]->getPosition();
            $mI = $bodies[$i]->getMass();

            for ($j = $i + 1; $j < $n; $j++) {
                $pJ = $bodies[$j]->getPosition();
                $mJ = $bodies[$j]->getMass();

                $dx = $pJ['x'] - $pI['x'];
                $dy = $pJ['y'] - $pI['y'];
                $dz = $pJ['z'] - $pI['z'];

                $dist = sqrt(($dx * $dx) + ($dy * $dy) + ($dz * $dz) + $eps2);
                $potential -= (self::G * $mI * $mJ) / $dist;
            }
        }

        return [
            'kinetic'   => $kinetic,
            'potential' => $potential,
            'total'     => $kinetic + $potential,
        ];
    }
}
