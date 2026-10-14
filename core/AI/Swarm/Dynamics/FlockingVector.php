<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Swarm/Dynamics/FlockingVector.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\AI\Swarm\Dynamics;

/**
 * Craig Reynolds Boids 2.0 Spatial Flocking Vector Calculator.
 *
 * Simulates collective motion and dynamic load balancing:
 *   - Separation: steer away from crowded peers
 *   - Alignment: steer towards average flock velocity
 *   - Cohesion: steer towards centroid of local flockmates
 */
class FlockingVector
{
    public function __construct(
        private readonly float $weightSeparation = 1.5,
        private readonly float $weightAlignment = 1.0,
        private readonly float $weightCohesion = 1.0,
        private readonly float $separationDistance = 3.0,
        private readonly float $maxSpeed = 2.0
    ) {
    }

    /**
     * Compute new velocity vector [vx, vy] based on local neighbors.
     *
     * @param array{x: float, y: float, vx: float, vy: float} $agent Current agent state
     * @param array<int, array{x: float, y: float, vx: float, vy: float}> $neighbors Neighbor agents
     * @return array{vx: float, vy: float}
     */
    public function computeVelocity(array $agent, array $neighbors): array
    {
        if (empty($neighbors)) {
            return ['vx' => $agent['vx'], 'vy' => $agent['vy']];
        }

        $sepX = 0.0;
        $sepY = 0.0;
        $aliX = 0.0;
        $aliY = 0.0;
        $cohX = 0.0;
        $cohY = 0.0;
        $n = count($neighbors);

        foreach ($neighbors as $nbr) {
            $dx = $agent['x'] - $nbr['x'];
            $dy = $agent['y'] - $nbr['y'];
            $dist = sqrt(($dx ** 2) + ($dy ** 2));

            // Separation (repulsive inverse distance)
            if ($dist > 0.001 && $dist < $this->separationDistance) {
                $sepX += ($dx / $dist) / $dist;
                $sepY += ($dy / $dist) / $dist;
            }

            // Alignment
            $aliX += $nbr['vx'];
            $aliY += $nbr['vy'];

            // Cohesion
            $cohX += $nbr['x'];
            $cohY += $nbr['y'];
        }

        // Averages
        $aliX /= $n;
        $aliY /= $n;
        $cohX = ($cohX / $n) - $agent['x'];
        $cohY = ($cohY / $n) - $agent['y'];

        // Composite acceleration
        $accX = ($this->weightSeparation * $sepX) + ($this->weightAlignment * $aliX) + ($this->weightCohesion * $cohX);
        $accY = ($this->weightSeparation * $sepY) + ($this->weightAlignment * $aliY) + ($this->weightCohesion * $cohY);

        $vx = $agent['vx'] + $accX;
        $vy = $agent['vy'] + $accY;

        // Clamp speed
        $speed = sqrt(($vx ** 2) + ($vy ** 2));
        if ($speed > $this->maxSpeed) {
            $vx = ($vx / $speed) * $this->maxSpeed;
            $vy = ($vy / $speed) * $this->maxSpeed;
        }

        return ['vx' => round($vx, 3), 'vy' => round($vy, 3)];
    }
}
