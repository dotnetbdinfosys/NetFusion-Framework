<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Spatial Computing & WebXR Hyper-Spatial Digital Twin
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Spatial\Physics;

use NetFusion\Core\Spatial\Math\Vector3;

class PhysicsWorld
{
    /** @var array<string, RigidBody> */
    protected array $bodies = [];
    public Vector3 $gravity;
    protected ?float $groundHeight = 0.0;

    public function __construct(?Vector3 $gravity = null)
    {
        $this->gravity = $gravity ?? new Vector3(0.0, -9.81, 0.0);
    }

    public function addBody(RigidBody $body): void
    {
        $this->bodies[$body->getId()] = $body;
    }

    public function removeBody(string $id): ?RigidBody
    {
        $b = $this->bodies[$id] ?? null;
        unset($this->bodies[$id]);
        return $b;
    }

    public function getBody(string $id): ?RigidBody
    {
        return $this->bodies[$id] ?? null;
    }

    /**
     * @return RigidBody[]
     */
    public function getBodies(): array
    {
        return array_values($this->bodies);
    }

    public function setGroundHeight(?float $height): void
    {
        $this->groundHeight = $height;
    }

    /**
     * Step the physics simulation forward by dt seconds
     */
    public function step(float $dt = 0.016): void
    {
        foreach ($this->bodies as $body) {
            $body->integrate($dt, $this->gravity);

            // Simple floor collision resolution
            if ($this->groundHeight !== null && $body->position->y < $this->groundHeight) {
                $body->position->y = $this->groundHeight;
                if ($body->velocity->y < 0.0) {
                    // Elastic rebound
                    $body->velocity->y = -$body->velocity->y * $body->restitution;
                    // Surface friction
                    $body->velocity->x *= (1.0 - $body->friction);
                    $body->velocity->z *= (1.0 - $body->friction);
                }
            }
        }
    }
}
