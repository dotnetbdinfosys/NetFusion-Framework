<?php
/**
 * NetFusion Framework - Enterprise Astro-Informatics & Celestial Mechanics Fabric
 *
 * Core orbital mechanics algorithms: Kepler solver, state vector conversion,
 * Hohmann interplanetary transfers, and Lagrange equilibrium points.
 *
 * @package    NetFusion\Core\Astro\Orbit
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Astro\Orbit;

use NetFusion\Core\Astro\Exceptions\KeplerConvergenceException;

class OrbitalMechanics
{
    /**
     * Solve Kepler's equation M = E - e * sin(E) for eccentric anomaly E using Newton-Raphson.
     *
     * @param float $meanAnomaly M in radians
     * @param float $eccentricity e (0 <= e < 1)
     * @param float $tolerance Convergence tolerance
     * @param int $maxIterations Maximum iterations
     * @return float Eccentric anomaly E in radians
     * @throws KeplerConvergenceException
     */
    public function solveKepler(
        float $meanAnomaly,
        float $eccentricity,
        float $tolerance = 1e-11,
        int $maxIterations = 100
    ): float {
        // Normalize M to [0, 2*pi)
        $m = fmod($meanAnomaly, 2.0 * M_PI);
        if ($m < 0.0) {
            $m += 2.0 * M_PI;
        }

        // Initial estimate E0
        $e = $eccentricity;
        $eVal = ($e < 0.8) ? $m : M_PI;

        for ($i = 0; $i < $maxIterations; $i++) {
            $f = $eVal - ($e * sin($eVal)) - $m;
            $fPrime = 1.0 - ($e * cos($eVal));

            if (abs($fPrime) < 1e-14) {
                break;
            }

            $delta = $f / $fPrime;
            $eVal -= $delta;

            if (abs($delta) < $tolerance) {
                return $eVal;
            }
        }

        throw KeplerConvergenceException::failedToConverge($meanAnomaly, $eccentricity, $maxIterations);
    }

    /**
     * Convert eccentric anomaly E to true anomaly nu.
     */
    public function eccentricToTrueAnomaly(float $eVal, float $eccentricity): float
    {
        $e = $eccentricity;
        $tanNu2 = sqrt((1.0 + $e) / (1.0 - $e)) * tan($eVal / 2.0);
        $nu = 2.0 * atan($tanNu2);

        if ($nu < 0.0) {
            $nu += 2.0 * M_PI;
        }

        return $nu;
    }

    /**
     * Convert classical Keplerian orbital elements to 3D Cartesian position and velocity state vectors.
     *
     * @return array{position: array{x: float, y: float, z: float}, velocity: array{x: float, y: float, z: float}}
     */
    public function elementsToCartesian(KeplerianElements $elements): array
    {
        $a  = $elements->getSemiMajorAxis();
        $e  = $elements->getEccentricity();
        $i  = $elements->getInclination();
        $ra = $elements->getLongitudeOfAscendingNode();
        $w  = $elements->getArgumentOfPeriapsis();
        $nu = $elements->getTrueAnomaly();
        $mu = $elements->getMu();

        // Semi-latus rectum: p = a * (1 - e^2)
        $p = $a * (1.0 - ($e * $e));

        // Radius in orbital plane: r = p / (1 + e * cos(nu))
        $rMag = $p / (1.0 + ($e * cos($nu)));

        // Position in perifocal frame (PQW)
        $rP = $rMag * cos($nu);
        $rQ = $rMag * sin($nu);

        // Velocity in perifocal frame (PQW)
        $vFac = sqrt($mu / $p);
        $vP = -$vFac * sin($nu);
        $vQ = $vFac * ($e + cos($nu));

        // Rotation matrix elements R = R_z(-ra) * R_x(-i) * R_z(-w)
        $cosRa = cos($ra);
        $sinRa = sin($ra);
        $cosI  = cos($i);
        $sinI  = sin($i);
        $cosW  = cos($w);
        $sinW  = sin($w);

        $px = $cosRa * $cosW - $sinRa * $sinW * $cosI;
        $py = $sinRa * $cosW + $cosRa * $sinW * $cosI;
        $pz = $sinW * $sinI;

        $qx = -$cosRa * $sinW - $sinRa * $cosW * $cosI;
        $qy = -$sinRa * $sinW + $cosRa * $cosW * $cosI;
        $qz = $cosW * $sinI;

        // Inertial Cartesian coordinates
        $x = $rP * $px + $rQ * $qx;
        $y = $rP * $py + $rQ * $qy;
        $z = $rP * $pz + $rQ * $qz;

        $vx = $vP * $px + $vQ * $qx;
        $vy = $vP * $py + $vQ * $qy;
        $vz = $vP * $pz + $vQ * $qz;

        return [
            'position' => ['x' => $x, 'y' => $y, 'z' => $z],
            'velocity' => ['x' => $vx, 'y' => $vy, 'z' => $vz],
        ];
    }

    /**
     * Compute Hohmann orbital transfer trajectory between two coplanar circular orbits.
     *
     * @param float $r1 Radius of initial circular orbit (meters)
     * @param float $r2 Radius of target circular orbit (meters)
     * @param float $mu Gravitational parameter of central primary body
     * @return array{
     *     delta_v1: float,
     *     delta_v2: float,
     *     total_delta_v: float,
     *     transfer_time_seconds: float,
     *     transfer_semi_major_axis: float
     * }
     */
    public function computeHohmannTransfer(float $r1, float $r2, float $mu = KeplerianElements::MU_SUN): array
    {
        $aTx = ($r1 + $r2) / 2.0;

        // Speeds in circular orbits
        $vc1 = sqrt($mu / $r1);
        $vc2 = sqrt($mu / $r2);

        // Speeds at periapsis and apoapsis of transfer ellipse
        $vTx1 = sqrt($mu * ((2.0 / $r1) - (1.0 / $aTx)));
        $vTx2 = sqrt($mu * ((2.0 / $r2) - (1.0 / $aTx)));

        $dv1 = abs($vTx1 - $vc1);
        $dv2 = abs($vc2 - $vTx2);
        $totalDv = $dv1 + $dv2;

        // Transfer time = half of transfer orbital period
        $tTx = M_PI * sqrt(pow($aTx, 3) / $mu);

        return [
            'delta_v1'                  => $dv1,
            'delta_v2'                  => $dv2,
            'total_delta_v'             => $totalDv,
            'transfer_time_seconds'     => $tTx,
            'transfer_semi_major_axis'  => $aTx,
        ];
    }

    /**
     * Compute the 5 Lagrange Equilibrium Points (L1..L5) for a circular restricted 3-body system.
     *
     * Coordinates are centered at the barycenter in the co-rotating frame with distance R = 1.0.
     *
     * @param float $m1 Primary body mass
     * @param float $m2 Secondary body mass
     * @param float $distance Distance between M1 and M2
     * @return array<string, array{x: float, y: float, description: string}>
     */
    public function computeLagrangePoints(float $m1, float $m2, float $distance = 1.0): array
    {
        $mu = $m2 / ($m1 + $m2); // Normalized mass ratio (usually mu << 1)
        $r = $distance;

        // Hill radius approximation: r_H = R * (mu / 3)^(1/3)
        $rH = $r * pow($mu / 3.0, 1.0 / 3.0);

        // L1: between M1 and M2
        $l1X = ($r * (1.0 - $mu)) - $rH;
        // L2: beyond M2
        $l2X = ($r * (1.0 - $mu)) + $rH;
        // L3: opposite side of M1
        $l3X = -($r * (1.0 + (5.0 * $mu / 12.0)));

        // L4 & L5: Equilateral triangle points (60 degrees ahead and behind M2)
        $l4X = $r * (0.5 - $mu);
        $l4Y = $r * (sqrt(3.0) / 2.0);

        $l5X = $l4X;
        $l5Y = -$l4Y;

        return [
            'L1' => ['x' => $l1X, 'y' => 0.0, 'description' => 'Collinear point between M1 and M2 (Solar observation, SOHO)'],
            'L2' => ['x' => $l2X, 'y' => 0.0, 'description' => 'Collinear point behind M2 (Deep space telescopes, JWST)'],
            'L3' => ['x' => $l3X, 'y' => 0.0, 'description' => 'Collinear point opposite to M2 behind M1'],
            'L4' => ['x' => $l4X, 'y' => $l4Y, 'description' => 'Trojan equilibrium point 60 deg ahead of M2 orbit'],
            'L5' => ['x' => $l5X, 'y' => $l5Y, 'description' => 'Trojan equilibrium point 60 deg behind M2 orbit'],
        ];
    }
}
